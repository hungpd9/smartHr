<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_night_shift')->comment('Ca làm việc tiêu chuẩn mặc định toàn công ty');
            $table->decimal('night_allowance_rate', 5, 2)->default(30.00)->after('is_default')->comment('Tỷ lệ phụ cấp ca đêm (%) theo Điều 98 BLLĐ');
            $table->decimal('night_bonus_amount', 12, 2)->default(0.00)->after('night_allowance_rate')->comment('Tiền thưởng / hỗ trợ bữa ăn ca đêm (VNĐ)');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('default_shift_id')->nullable()->after('branch_id')->constrained('shifts')->nullOnDelete()->comment('Ca làm việc tiêu chuẩn mặc định riêng cho phòng ban');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_shift_id');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn([
                'is_default',
                'night_allowance_rate',
                'night_bonus_amount',
            ]);
        });
    }
};
