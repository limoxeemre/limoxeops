/*
 * Transfer Ops – Leaflet harita bileşenleri (Alpine).
 * Leaflet & Geoman ilk ihtiyaçta (public/vendor) yüklenir; derleme gerektirmez.
 */
(() => {
    // Kütüphaneler public/vendor altında yerel olarak servis edilir (CDN bağımlılığı yok).
    // Bu dosya /js/app/transfer-maps.js olarak yayınlanır → ../../vendor/
    const VENDOR = new URL('../../vendor/', document.currentScript?.src || window.location.origin + '/js/app/x.js').href
    const LEAFLET = VENDOR + 'leaflet/'
    const GEOMAN = VENDOR + 'leaflet-geoman/'
    const DEFAULT_CENTER = [36.8969, 30.7133] // Antalya
    const loaded = {}

    const loadCss = (href) => {
        if (document.querySelector(`link[href="${href}"]`)) return
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = href
        document.head.appendChild(link)
    }

    const loadScript = (src) =>
        (loaded[src] ??= new Promise((resolve, reject) => {
            const s = document.createElement('script')
            s.src = src
            s.onload = resolve
            s.onerror = reject
            document.head.appendChild(s)
        }))

    window.trLoadLeaflet = async (withEditor = false) => {
        loadCss(LEAFLET + 'leaflet.css')
        await loadScript(LEAFLET + 'leaflet.js')
        if (withEditor) {
            loadCss(GEOMAN + 'leaflet-geoman.css')
            await loadScript(GEOMAN + 'leaflet-geoman.min.js')
        }
        return window.L
    }

    const baseMap = (el, center, zoom) => {
        const map = L.map(el, { scrollWheelZoom: true, zoomControl: true }).setView(center || DEFAULT_CENTER, zoom || 10)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map)
        // Sekme/modal içinde açılınca boyut düzeltmesi
        new ResizeObserver(() => map.invalidateSize()).observe(el)
        setTimeout(() => map.invalidateSize(), 250)
        return map
    }

    const drawZones = (map, zones, { interactive = false, highlightId = null } = {}) => {
        const group = L.featureGroup()
        ;(zones || []).forEach((z) => {
            if (!z.polygon || z.polygon.length < 3) {
                if (z.center_lat && z.radius_km) {
                    L.circle([z.center_lat, z.center_lng], {
                        radius: z.radius_km * 1000, color: z.color, weight: 1, fillOpacity: 0.08, interactive,
                    }).bindTooltip(z.name).addTo(group)
                }
                return
            }
            const isHighlighted = highlightId === z.id
            L.polygon(z.polygon, {
                color: z.color,
                weight: isHighlighted ? 3 : 1.5,
                dashArray: isHighlighted ? null : '4 4',
                fillOpacity: isHighlighted ? 0.25 : 0.08,
                interactive,
            })
                .bindTooltip(`<strong>${z.name}</strong>${z.type_label ? '<br>' + z.type_label : ''}`, { sticky: true })
                .addTo(group)
        })
        group.addTo(map)
        return group
    }

    const nominatim = async (path, params) => {
        const url = new URL('https://nominatim.openstreetmap.org/' + path)
        Object.entries({ format: 'jsonv2', 'accept-language': 'tr', ...params }).forEach(([k, v]) => url.searchParams.set(k, v))
        const res = await fetch(url)
        return res.ok ? res.json() : null
    }

    document.addEventListener('alpine:init', () => {
        /*
         * Nokta seçici: state = { lat, lng }
         * Haritaya tıkla / işaretçiyi sürükle / adres ara.
         */
        Alpine.data('trMapPicker', ({ state, zones, addressStatePath, readOnly = false, height = 320 }) => {
            let map, marker

            return {
                state,
                query: '',
                results: [],
                searching: false,

                async init() {
                    await trLoadLeaflet()
                    map = baseMap(this.$refs.map, this.hasPoint() ? [this.state.lat, this.state.lng] : null, this.hasPoint() ? 14 : 10)
                    drawZones(map, zones)

                    if (!readOnly) {
                        map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng, true))
                    }

                    this.syncMarker(false)
                    this.$watch('state', () => this.syncMarker(true))
                },

                hasPoint() {
                    return this.state && this.state.lat !== null && this.state.lat !== undefined && this.state.lat !== ''
                },

                setPoint(lat, lng, reverseGeocode = false) {
                    this.state = { lat: Number(Number(lat).toFixed(7)), lng: Number(Number(lng).toFixed(7)) }
                    if (reverseGeocode && addressStatePath) this.reverse(lat, lng)
                },

                syncMarker(pan) {
                    if (!map) return
                    if (!this.hasPoint()) {
                        marker?.remove()
                        marker = null
                        return
                    }
                    const pos = [this.state.lat, this.state.lng]
                    if (!marker) {
                        marker = L.marker(pos, { draggable: !readOnly }).addTo(map)
                        marker.on('dragend', () => {
                            const p = marker.getLatLng()
                            this.setPoint(p.lat, p.lng, true)
                        })
                    } else {
                        marker.setLatLng(pos)
                    }
                    if (pan) map.setView(pos, Math.max(map.getZoom(), 14))
                },

                async search() {
                    if (this.query.trim().length < 3) return
                    this.searching = true
                    try {
                        this.results = (await nominatim('search', { q: this.query, limit: 6, countrycodes: 'tr' })) || []
                    } finally {
                        this.searching = false
                    }
                },

                choose(result) {
                    this.results = []
                    this.query = ''
                    this.setPoint(result.lat, result.lon)
                    if (addressStatePath) this.$wire.set(addressStatePath, result.display_name)
                },

                async reverse(lat, lng) {
                    const r = await nominatim('reverse', { lat, lon: lng, zoom: 18 })
                    if (r?.display_name) this.$wire.set(addressStatePath, r.display_name)
                },

                locateMe() {
                    navigator.geolocation?.getCurrentPosition((p) => this.setPoint(p.coords.latitude, p.coords.longitude, true))
                },
            }
        })

        /*
         * Bölge poligon çizici: state = [[lat, lng], ...]
         */
        Alpine.data('trZoneEditor', ({ state, zones, color }) => {
            let map, layer

            return {
                state,
                pointCount: 0,

                async init() {
                    await trLoadLeaflet(true)
                    map = baseMap(this.$refs.map, null, 10)
                    const others = drawZones(map, zones)

                    map.pm.setLang('tr')
                    map.pm.addControls({
                        position: 'topleft',
                        drawMarker: false, drawCircleMarker: false, drawPolyline: false, drawCircle: false, drawText: false,
                        drawRectangle: true, drawPolygon: true, cutPolygon: false, rotateMode: false,
                        editMode: true, dragMode: true, removalMode: true,
                    })
                    map.pm.setPathOptions({ color, fillOpacity: 0.25 })

                    if (this.state?.length >= 3) {
                        this.setLayer(L.polygon(this.state, { color, fillOpacity: 0.25 }))
                        map.fitBounds(layer.getBounds(), { padding: [30, 30] })
                    } else if (others.getLayers().length) {
                        map.fitBounds(others.getBounds(), { padding: [30, 30] })
                    }

                    map.on('pm:create', (e) => {
                        layer?.remove() // tek poligon
                        this.setLayer(e.layer)
                        this.commit()
                    })
                    map.on('pm:remove', (e) => {
                        if (e.layer === layer) {
                            layer = null
                            this.state = []
                            this.pointCount = 0
                        }
                    })
                },

                setLayer(l) {
                    layer = l.addTo(map)
                    layer.on('pm:edit pm:dragend pm:markerdragend pm:vertexremoved', () => this.commit())
                    this.pointCount = layer.getLatLngs()[0].length
                },

                commit() {
                    const pts = layer.getLatLngs()[0].map((p) => [Number(p.lat.toFixed(6)), Number(p.lng.toFixed(6))])
                    this.pointCount = pts.length
                    this.state = pts
                },

                clear() {
                    layer?.remove()
                    layer = null
                    this.state = []
                    this.pointCount = 0
                },
            }
        })

        /* Salt okunur genel harita: bölgeler + lokasyonlar + (opsiyonel) transfer güzergahı */
        Alpine.data('trOverviewMap', ({ zones, markers = [], line = null, height }) => ({
            async init() {
                await trLoadLeaflet()
                const map = baseMap(this.$refs.map, null, 9)
                const group = drawZones(map, zones, { interactive: true })

                markers.forEach((m) => {
                    L.circleMarker([m.lat, m.lng], {
                        radius: m.size || 6, color: '#fff', weight: 2, fillColor: m.color || '#0ea5e9', fillOpacity: 1,
                    }).bindTooltip(m.label).addTo(group)
                })

                if (line) {
                    L.polyline(line, { color: '#0ea5e9', weight: 4, dashArray: '8 8' }).addTo(group)
                }

                if (group.getLayers().length) map.fitBounds(group.getBounds(), { padding: [30, 30] })
            },
        }))
    })
})()
