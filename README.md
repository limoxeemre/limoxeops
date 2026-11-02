# Transfer Ops: Havalimanı Transfer Operasyon Sistemi

Laravel 13 + Filament 5 ile yazılmış, turizm firmaları için havalimanı ↔ otel transfer rezervasyon ve operasyon sistemi.

- **REST API (v1):** web sitesi, mobil uygulama, B2B acenteler ve sürücü uygulaması için.
- **Filament yönetim paneli:** rezervasyon, operasyon panosu, sürücü atama, fiyat ve bölge yönetimi.
- **Bölge (poligon) bazlı sabit rota fiyatlandırması:** rota tanımlı değilse km bazlı yedek tarife kullanılır.

## Hızlı başlangıç

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # veya .env içinde MySQL/PostgreSQL ayarlayın
php artisan migrate --seed              # Antalya demo verisi + demo API token'ları ekrana yazılır
php artisan filament:assets
php artisan storage:link
php artisan serve
```

Panel adresi `http://localhost:8000/admin`, giriş bilgileri `admin@example.com` / `password`.
E-postalar kuyruğa alınır. Kuyruğu çalıştırmak için `php artisan queue:work` kullanın.

Testler için `php artisan test` çalıştırın. Toplam 53 test var: fiyat motoru, API, sürücü akışı ve panelin tüm sayfaları.

---

## Mimari

```
Reservation (müşteri, ödeme, toplam fiyat)
 ├── Transfer  (bacak: gidiş)  ← operasyon birimi: sürücü/araç/durum burada
 ├── Transfer  (bacak: dönüş)  ← gidiş-dönüşte ikinci bacak
 └── ReservationExtra (bebek koltuğu vb.)
```

Gidiş ve dönüş bacakları, farklı günlerde farklı sürücülerle yapılan ayrı işlerdir. Bu yüzden atama ve durum takibi
rezervasyon seviyesinde değil, **bacak (Transfer)** seviyesinde yapılır.

| Klasör | İçerik |
|---|---|
| `app/Services/Geo` | `GeoMath` (point-in-polygon, haversine), `ZoneResolver` (koordinat → bölge), `Waypoint` |
| `app/Services/Pricing` | `PriceCalculator` (fiyat motoru) ve teklif DTO'ları |
| `app/Services/Reservations` | `ReservationService` (oluştur/iptal), `TransferService` (atama, durum makinesi), `PickupTimeSuggester` |
| `app/Enums` | Durumlar. Filament etiket, renk ve ikonları da buradan gelir |
| `app/Filament` | Panel kaynakları, sayfalar, widget'lar, özel harita alanları |
| `config/transfer.php` | Para birimi, km yedek tarifesi, havalimanı tamponu, iptal süresi vb. |

### Fiyat hesaplama akışı

1. Alış ve bırakış koordinatları **bölgeye** çevrilir: nokta hangi poligonun içindeyse o bölge seçilir.
   Bölgeler çakışırsa `priority` değeri yüksek olan, eşitse alanı küçük olan kazanır. Örneğin havalimanı poligonu,
   içinde bulunduğu şehir poligonuna göre önceliklidir.
2. **Sabit rota** (bölge → bölge × araç tipi) fiyatı aranır. Çift yönlü rotalar ters yönde de geçerlidir.
3. Rota yoksa araç tipinin **km tarifesi** uygulanır: `max(min, açılış + km × birim)`. Mesafe kuş uçuşu
   × `road_factor` ile hesaplanır. Bu yedek tarife `TRANSFER_DISTANCE_FALLBACK=false` ile kapatılabilir.
4. **Fiyat kuralları** taban fiyata eklenir. Kural tipleri: gece tarifesi (gece yarısını geçen saat aralığı),
   sezon tarihleri, haftanın günleri, belirli araç tipi veya bölge. Değer yüzde veya sabit tutar olabilir;
   pozitif değer zam, negatif değer indirimdir.
5. Dönüş bacağı kendi tarih ve saatine göre ayrıca hesaplanır. Örneğin gece dönüşüne gece tarifesi uygulanır.
6. Ekstralar (bacak başına veya rezervasyon başına) ve acente indirimi eklenir.

İstemciden gelen fiyata hiçbir zaman güvenilmez; rezervasyon oluşturulurken fiyat sunucuda yeniden hesaplanır.
Yalnızca panelden manuel fiyat girilebilir.

### Alış saati önerisi

Uçuş saati verilip alış saati verilmezse:

- **Gelişte:** alış saati = iniş saati.
- **Gidişte:** alış saati = kalkış − yol süresi − 150 dk havalimanı tamponu (5 dakikaya yuvarlanır).

---

## API (v1)

Herkese açık uçlar rate-limit'lidir. `Authorization: Bearer <token>` ile acente veya sürücü olarak çağrılır.
Token'lar panelde Acenteler veya Sürücüler sayfasındaki **API Token** aksiyonuyla üretilir.

| Metot | Uç | Açıklama |
|---|---|---|
| GET | `/api/v1/locations?search=lara&type=hotel` | Lokasyon autocomplete (otel, terminal, liman) |
| GET | `/api/v1/zones`, `/vehicle-types`, `/extras` | Katalog |
| GET | `/api/v1/geo/zone?lat=&lng=` | Koordinat hizmet bölgesinde mi? |
| POST | `/api/v1/quotes` | Uygun tüm araç tipleri için fiyat (acente token'ı varsa indirimli) |
| POST | `/api/v1/reservations` | Rezervasyon oluştur (201 + rezervasyon kodu) |
| GET | `/api/v1/reservations/{code}?email=` | Rezervasyon sorgula (acente token'ı varsa e-posta gerekmez) |
| POST | `/api/v1/reservations/{code}/cancel` | Ücretsiz iptal süresi içindeyse iptal |
| GET | `/api/v1/agency/reservations` | Acentenin kendi rezervasyonları (`from`, `to`, `status`) |
| GET | `/api/v1/driver/transfers?date=` | Sürücünün günlük işleri (yolcu bilgisi ve tahsilat tutarıyla) |
| PATCH | `/api/v1/driver/transfers/{id}/status` | `en_route` → `on_board` → `completed` / `no_show` (+ lat/lng) |

Teklif isteği örneği:

```json
POST /api/v1/quotes
{
  "pickup":  { "location_id": 2 },
  "dropoff": { "lat": 36.86, "lng": 31.06, "address": "Belek, X Otel" },
  "pickup_at": "2026-11-05 02:30",
  "return_pickup_at": "2026-11-12 14:00",
  "adults": 2, "children": 1, "luggage": 3,
  "extras": [{ "id": 1, "quantity": 1 }]
}
```

Yanıtta her araç tipi için bacak bazında taban fiyat, uygulanan kurallar (örn. `Gece Tarifesi +7`),
fiyat kaynağı (`fixed_route` / `distance`), ekstralar, indirim ve toplam yer alır.
Rezervasyon oluşturmak için aynı gövdeye `vehicle_type_id`, `passenger_name`, `passenger_phone`,
`passenger_email` (opsiyonel olarak `flight_number`, `customer_notes`, `agency_reference`) eklenir.

Transfer durum makinesi:

```
pending → assigned → en_route → on_board → completed
                         └────→ no_show
(pending/assigned → cancelled)
```

Her geçiş `transfer_status_logs` tablosuna kim, ne zaman ve hangi konumda yaptığı bilgisiyle loglanır.

---

## Panel (Filament)

- **Panel (dashboard):** bugünkü transferler, 24 saat içinde ataması yapılmamış işler, onay bekleyen
  rezervasyonlar, aylık ciro, sıradaki 12 saatin canlı listesi ve 14 günlük yoğunluk grafiği.
- **Operasyon Panosu:** Bugün / Yarın / Atanmamış / Yolda sekmeleri. Tarih veya sürücüye göre gruplama,
  30 saniyede bir otomatik yenileme. 12 saat içinde ataması yapılmamış satırlar kırmızı vurgulanır.
  - **Ata** modalında her sürücünün müsaitliği ve çakışan işlerin saatleri görünür.
    Yalnızca yolcu sayısına yeterli kapasitedeki araçlar listelenir.
  - Toplu sürücü atama, durum geçişleri, saat/uçuş düzenleme, Google Maps yol tarifi ve WhatsApp aksiyonları vardır.
- **Rezervasyon oluşturma:** kayıtlı lokasyon seçilebilir ya da harita üzerinden nokta işaretlenebilir
  (adres arama ve ters geocoding var). Seçilen noktanın bölgesi anında gösterilir. Fiyat dökümü form
  doldurulurken canlı güncellenir. Araç seçenekleri fiyatlarıyla listelenir; kapasitesi yetmeyen ya da
  tarifesi olmayan araçlar pasif görünür. Uçuş saatinden alış saati önerisi ve manuel fiyat seçeneği de vardır.
- **Fiyat Hesapla:** çağrı merkezi için hızlı teklif ekranı. Seçilen teklif tek tıkla rezervasyon formuna aktarılır.
- **Bölgeler:** poligon harita üzerinde çizilir ve düzenlenir (Leaflet + Geoman). Diğer bölgeler referans
  olarak kesikli çizgiyle görünür.
- **Rotalar & Fiyatlar:** Excel benzeri fiyat matrisi. Her araç tipi bir sütundur ve hücreler tablodan
  doğrudan düzenlenir.
- Fiyat kuralları, ekstralar, araç tipleri, sürücüler, araçlar ve acenteler (indirim oranı + API token) için
  ayrı yönetim ekranları vardır.
- Global arama (`Ctrl+K`) ile rezervasyon kodu, yolcu adı, telefon, e-posta veya acente referansı aranabilir.

Harita kütüphaneleri `public/vendor` altından yerel olarak servis edilir; CDN bağımlılığı yoktur. Harita
karoları OpenStreetMap'ten, adres arama Nominatim'den gelir. Yoğun kullanımda kendi karo sunucunuzu veya
ticari bir sağlayıcı kullanın.

## Laravel Cloud'a kurulum

1. [cloud.laravel.com](https://cloud.laravel.com) → **New application** → bu GitHub deposunu seçin (`main` dalı).
2. **Database** ekleyin (Laravel MySQL veya Serverless Postgres). Bağlantı değişkenleri otomatik tanımlanır.
3. **Environment variables** bölümüne ekleyin:
   ```
   APP_NAME="Transfer Ops"
   APP_LOCALE=tr
   APP_TIMEZONE=Europe/Istanbul
   TRANSFER_CURRENCY=EUR
   ```
4. **Build commands** (varsayılanların sonuna):
   ```
   php artisan filament:assets
   ```
5. **Deploy commands:**
   ```
   php artisan migrate --force
   ```
6. İlk deploy'dan sonra bir kez **Commands** sekmesinden demo veriyi yükleyin
   (admin kullanıcısı + Antalya bölgeleri + örnek rezervasyonlar; çıktıda demo API token'ları görünür):
   ```
   php artisan db:seed --force
   ```
7. E-postalar kuyruğa alındığı için **Queue worker** açın ve `MAIL_*` değişkenlerini girin.
8. Girişten sonra `admin@example.com` şifresini hemen değiştirin.

## Production için yapılacaklar

- `User::canAccessPanel` şu an her kullanıcıya izin veriyor. Rol ve izin yönetimi ekleyin
  (örn. `bezhansalleh/filament-shield`).
- Kuyruk worker'ı ve e-posta sağlayıcısı kurulmalı. Sürücüye SMS veya WhatsApp bildirimi için
  `TransferService::assign` uygun bir genişletme noktasıdır.
- Gerçek karayolu mesafesi gerekiyorsa `PriceCalculator::roadDistance` OSRM veya Google Distance Matrix ile değiştirilebilir.
- Online ödeme (iyzico, Stripe vb.) `payment_status` / `payment_method` alanlarına bağlanabilir.
- Uçuş takibi (rötar olduğunda alış saatini otomatik güncelleme) eklenebilir. `flight_number` alanı hazır.
