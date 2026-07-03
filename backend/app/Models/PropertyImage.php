<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PropertyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'image_path',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        try {
            if (Storage::disk('public')->exists($this->image_path)) {
                return Storage::disk('public')->url($this->image_path);
            }

            if ($this->shouldUseS3()) {
                return Storage::disk('s3')->temporaryUrl(
                    $this->image_path,
                    now()->addMinutes(30)
                );
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public static function uploadDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    public static function createForProperty(Property $property, UploadedFile $image): self
    {
        $path = $image->store('property_images', self::uploadDisk());
        if (!$path) {
            throw ValidationException::withMessages([
                'images' => ['تعذر رفع الصور. يرجى المحاولة مرة أخرى.'],
            ]);
        }

        return self::create([
            'property_id' => $property->id,
            'image_path'  => $path,
            'is_main'     => !$property->images()->exists(),
        ]);
    }

    public function toApiArray(): array
    {
        return [
            'id'       => $this->id,
            'url'      => $this->url,
            'is_main'  => $this->is_main,
        ];
    }

    public function deleteStoredFile(): void
    {
        if (!$this->image_path) {
            return;
        }

        try {
            if (Storage::disk('public')->exists($this->image_path)) {
                Storage::disk('public')->delete($this->image_path);
                return;
            }

            if ($this->shouldUseS3()) {
                Storage::disk('s3')->delete($this->image_path);
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function shouldUseS3(): bool
    {
        return config('filesystems.default') === 's3'
            && filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.secret'))
            && filled(config('filesystems.disks.s3.region'))
            && filled(config('filesystems.disks.s3.bucket'));
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
