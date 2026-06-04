<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_feedback_report_id')
                ->constrained('user_feedback_reports')
                ->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        $map = [
            'suggestion' => 'improvement',
            'wish' => 'feature',
            'missing' => 'feature',
        ];

        DB::table('user_feedback_reports')->orderBy('id')->chunkById(100, function ($rows) use ($map) {
            foreach ($rows as $row) {
                if ($row->path && $row->disk) {
                    DB::table('user_feedback_report_attachments')->insert([
                        'user_feedback_report_id' => $row->id,
                        'disk' => $row->disk,
                        'path' => $row->path,
                        'original_name' => $row->original_name ?? 'csatolmany',
                        'mime' => $row->mime,
                        'size_bytes' => $row->size_bytes ?? 0,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }

                $category = $map[$row->category] ?? $row->category;
                if ($category !== $row->category) {
                    DB::table('user_feedback_reports')->where('id', $row->id)->update(['category' => $category]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback_report_attachments');
    }
};
