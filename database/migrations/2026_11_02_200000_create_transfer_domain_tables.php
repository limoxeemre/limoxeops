<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * BÖLGELER: Haritada çizilen poligonlar (veya merkez + yarıçap).
         * Fiyatlandırma bölgeden bölgeye yapılır; bir koordinat hangi
         * poligonun içindeyse o bölgeye aittir. Çakışmalarda priority büyük olan kazanır
         * (ör. havalimanı poligonu, içinde bulunduğu şehir poligonundan önce gelir).
         */
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('type', 20)->index();
            $table->string('color', 9)->default('#3b82f6');
            $table->json('polygon')->nullable();          // [[lat, lng], ...]
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->decimal('radius_km', 8, 2)->nullable(); // poligon yoksa daire ile eşleşme
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* LOKASYONLAR: Otel, terminal, liman gibi sık kullanılan noktalar (autocomplete için). */
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->string('iata_code', 3)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('max_passengers');
            $table->unsignedTinyInteger('max_luggage');
            // Km bazlı yedek tarife (sabit rota yoksa)
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('per_km_rate', 10, 2)->default(0);
            $table->decimal('min_fare', 10, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ROTALAR: Bölgeden bölgeye sabit fiyat matrisi. */
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('to_zone_id')->constrained('zones')->cascadeOnDelete();
            $table->boolean('is_bidirectional')->default(true);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['from_zone_id', 'to_zone_id']);
        });

        Schema::create('route_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['route_id', 'vehicle_type_id']);
        });

        /* FİYAT KURALLARI: Gece tarifesi, sezon, hafta sonu, araç tipine özel indirim vb. */
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('adjustment_type', 10);      // percent | fixed
            $table->decimal('value', 10, 2);            // + zam, - indirim
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->time('time_from')->nullable();      // gece yarısını geçebilir (22:00 - 06:00)
            $table->time('time_to')->nullable();
            $table->json('days_of_week')->nullable();   // [1..7] ISO, null = her gün
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete(); // alış veya bırakış bölgesi
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('per_leg')->default(true); // gidiş-dönüşte her bacak için ayrı ücret
            $table->unsignedTinyInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ACENTELER: B2B iş ortakları, API token ile rezervasyon yapar. */
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('license_number', 50)->nullable();
            $table->json('languages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete(); // varsayılan sürücü
            $table->string('plate', 20)->unique();
            $table->string('brand_model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* REZERVASYON: Müşteri/ödeme seviyesindeki kayıt. 1 (tek yön) veya 2 (gidiş-dönüş) transfer bacağı içerir. */
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agency_reference')->nullable();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->string('source', 20);
            $table->string('trip_type', 20);

            $table->string('passenger_name');
            $table->string('passenger_phone', 30);
            $table->string('passenger_email');
            $table->string('passenger_country', 2)->nullable();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->unsignedTinyInteger('luggage')->default(0);

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3);
            $table->json('price_breakdown')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method', 20)->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('passenger_email');
        });

        /* TRANSFER (BACAK): Operasyonun asıl birimi. Sürücü/araç ataması bu seviyede yapılır. */
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('status', 20)->index();
            $table->dateTime('pickup_at')->index();
            $table->string('flight_number', 15)->nullable();
            $table->dateTime('flight_time')->nullable();

            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('pickup_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);

            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);

            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_source', 20);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->decimal('price', 10, 2);

            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->text('driver_notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'pickup_at']);
            $table->index(['status', 'pickup_at']);
        });

        Schema::create('reservation_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        /* Durum geçmişi: kim, ne zaman, hangi durumu değiştirdi (operasyon denetimi için). */
        Schema::create('transfer_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->nullableMorphs('causer');
            $table->string('note')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['transfer_status_logs', 'reservation_extras', 'transfers', 'reservations', 'vehicles', 'drivers',
            'agencies', 'extras', 'pricing_rules', 'route_prices', 'routes', 'vehicle_types', 'locations', 'zones'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
