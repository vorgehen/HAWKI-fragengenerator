<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

use Carbon\Carbon;

class MakeAnnouncement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:make
                            {title : Announcement Title}';


    protected $description = 'Create a new announcement Blade view';

    public function handle()
    {
        $title = $this->argument('title');
        $view = Str::slug($this->argument('title')); // Make safe filename!
        $time = Carbon::now()->format('Y-m-d-His');
        $name = "{$time}-{$view}";
        $folderName = "announcements/$name";
        // Ensure the directory exists
        if (!is_dir(resource_path($folderName))) {
            mkdir(resource_path($folderName), 0755, true);
        }

        $availableLanguages = config('locale.langs');

        foreach ($availableLanguages as $l) {
            $lang = $l['id'];
            $filePath = resource_path("$folderName/$lang.md");

            if (!file_exists($filePath)) {
                file_put_contents($filePath,
                    "<!-- Announcement: {$this->argument('title')} -->\n\n" .
                    "<!-- ENTER ANNOUNCEMENT IN CONTENT <<< $lang >>> HERE: -->\n" .
                    "#### $title"
                );
            }

            // Open the file in nano for editing
            $this->info("Opening $filePath in nano...");
            system("nano " . escapeshellarg(resource_path("$folderName/$lang.md")));
        }

        $this->line(resource_path("$folderName"));
    }
}
