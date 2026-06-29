<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SyncSeedImages extends Command
{
    protected $signature = 'seed:sync-images';
    protected $description = 'Copy checked-in seed property images into public storage';

    public function handle(): int
    {
        $images = [
            'شقة عائلية قريبة من البحر' => 'apartment.jpeg',
            'بيت مستقل في بيت لاهيا' => 'villa.jpeg',
            'شقة صغيرة في دير البلح' => 'small-apartment.webp',
            'محل تجاري في خانيونس' => 'commercial.webp',
        ];

        $synced = 0;

        foreach ($images as $title => $fileName) {
            $property = Property::where('title', $title)->first();

            if (!$property) {
                continue;
            }

            $source = database_path('seeders/assets/property-images/' . $fileName);
            $target = 'property_images/seed-' . $property->id . '-' . $fileName;

            if (!File::exists($source)) {
                $this->warn("Missing seed image: {$source}");
                continue;
            }

            Storage::disk('public')->put($target, File::get($source));
            $synced++;
        }

        $this->info("Synced {$synced} seed images.");

        return self::SUCCESS;
    }
}
