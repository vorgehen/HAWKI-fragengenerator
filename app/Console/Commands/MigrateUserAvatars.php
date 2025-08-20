<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use Throwable;

class MigrateUserAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:avatars
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--force : Force migration even if target file exists}
                            {--cleanup : Delete old avatar files after successful migration}
                            {--user= : Migrate only specific user by ID or username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate user avatars from old structure to new AvatarStorageService structure';

    protected AvatarStorageService $avatarStorage;
    protected int $migratedCount = 0;
    protected int $skippedCount = 0;
    protected int $errorCount = 0;

    public function __construct(AvatarStorageService $avatarStorage)
    {
        parent::__construct();
        $this->avatarStorage = $avatarStorage;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $cleanup = $this->option('cleanup');
        $specificUser = $this->option('user');

        $this->info('Starting avatar migration...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No files will be actually migrated');
        }

        // Get users based on options
        $query = User::whereNotNull('avatar_id')->where('avatar_id', '!=', '');

        if ($specificUser) {
            // Try to find user by ID or username
            if (is_numeric($specificUser)) {
                $query->where('id', $specificUser);
            } else {
                $query->where('username', $specificUser);
            }
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found with avatar_id set.');
            return 0;
        }

        $this->info("Found {$users->count()} users with avatar_id set.");

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            $this->migrateUserAvatar($user, $isDryRun, $force, $cleanup);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->displaySummary($isDryRun);

        return 0;
    }

    protected function migrateUserAvatar(User $user, bool $isDryRun, bool $force, bool $cleanup = false): void
    {
        try {
            $avatarId = trim($user->avatar_id);
            $username = $user->username ?? $user->email; // Fallback to email if no username

            // Skip if avatar_id is empty after trimming
            if (empty($avatarId)) {
                if ($this->output->isVerbose()) {
                    $this->line("Skipping user {$user->id} ({$username}) - empty avatar_id");
                }
                $this->skippedCount++;
                return;
            }

            // Clean username for use as folder name (remove special characters)
            $cleanUsername = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);

            // Check if old avatar file exists
            $oldAvatarPath = "public/profile_avatars/{$avatarId}";

            if (!Storage::disk('local')->exists($oldAvatarPath)) {
                $this->error("Avatar file not found for user {$user->id} (username: {$username}): {$oldAvatarPath}");
                $this->skippedCount++;
                return;
            }

            // Check if new avatar already exists (unless force is used)
            if (!$force) {
                $existingFile = $this->avatarStorage->retrieveFile('profile_avatars', $cleanUsername, $avatarId);
                if ($existingFile !== false) {
                    if ($this->output->isVerbose()) {
                        $this->line("Avatar already exists for user {$username}, skipping...");
                    }
                    $this->skippedCount++;
                    return;
                }
            }

            if ($isDryRun) {
                $this->line("Would migrate: User {$user->id} ({$username}) - {$oldAvatarPath} -> profile_avatars/{$cleanUsername}/{$avatarId}");
                $this->migratedCount++;
                return;
            }

            // Get the file content
            $fileContent = Storage::disk('local')->get($oldAvatarPath);

            if (!$fileContent) {
                $this->error("Could not read avatar file for user {$username}: {$oldAvatarPath}");
                $this->errorCount++;
                return;
            }

            // Determine file extension from original file or detect from content
            $extension = $this->getFileExtension($oldAvatarPath);
            if (!$extension) {
                // Try to detect from file content
                $extension = $this->detectFileExtension($fileContent) ?? 'jpg';
            }

            // Store using AvatarStorageService
            $stored = $this->avatarStorage->storeFile(
                file: $fileContent,
                category: 'profile_avatars',
                name: $cleanUsername,
                uuid: $avatarId
            );

            if ($stored) {
                if ($this->output->isVerbose()) {
                    $this->line("✓ Migrated avatar for user {$username}");
                }
                $this->migratedCount++;

                // Delete old file after successful migration if cleanup is enabled
                if ($cleanup) {
                    if (Storage::disk('local')->delete($oldAvatarPath)) {
                        if ($this->output->isVerbose()) {
                            $this->line("  ✓ Cleaned up old file: {$oldAvatarPath}");
                        }
                    } else {
                        $this->warn("  ⚠ Failed to delete old file: {$oldAvatarPath}");
                    }
                }
            } else {
                $this->error("Failed to store avatar for user {$username} using AvatarStorageService");
                $this->errorCount++;
            }

        } catch (Throwable $e) {
            $this->error("Error migrating avatar for user {$user->id}: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    protected function getFileExtension(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension ?: null;
    }

    protected function detectFileExtension(string $content): ?string
    {
        // Detect file type from content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
        ];

        return $extensions[$mimeType] ?? null;
    }

    protected function displaySummary(bool $isDryRun): void
    {
        $action = $isDryRun ? 'Would be migrated' : 'Migrated';

        $this->info('Migration Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                [$action, $this->migratedCount],
                ['Skipped', $this->skippedCount],
                ['Errors', $this->errorCount],
                ['Total', $this->migratedCount + $this->skippedCount + $this->errorCount]
            ]
        );

        if ($this->errorCount > 0) {
            $this->warn("There were {$this->errorCount} errors during migration. Check the output above for details.");
        }

        if (!$isDryRun && $this->migratedCount > 0) {
            $this->info("Successfully migrated {$this->migratedCount} avatars to the new storage structure.");
            $this->comment('Note: Old avatar files were not deleted. You may want to clean them up manually after verifying the migration.');
        }

        if ($isDryRun) {
            $this->info('Run without --dry-run to perform the actual migration.');
        }
    }
}
