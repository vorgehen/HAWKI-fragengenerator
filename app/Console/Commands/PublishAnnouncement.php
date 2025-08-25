<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Announcements\AnnouncementService;

class PublishAnnouncement extends Command
{
    protected $signature = 'announcement:publish
                            {title : Announcement Title}
                            {view : The Blade view reference (e.g. announcements.terms_update)}
                            {--type=info : Announcement type (force, success, warning, error, info)}
                            {--global : Make this a global announcement}
                            {--users=* : Target user IDs (if not global)}
                            {--start= : Start datetime (Y-m-d H:i:s)}
                            {--expire= : Expire datetime (Y-m-d H:i:s)}';

    protected $description = 'Create a new announcement entry referencing a Blade view';

    public function handle(AnnouncementService $service)
    {

        $announcement = $service->createAnnouncement(
            $this->argument('title'),
            $this->argument('view'),
            $this->option('type'),
            $this->option('global'),
            $this->option('users') ?: null,
            $this->option('start'),
            $this->option('expire')
        );

        $this->info("✅ Announcement [{$announcement->view}] created with ID {$announcement->id}");
    }
}
