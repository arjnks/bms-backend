<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TestR2UrlCommand extends Command
{
    protected $signature = 'test:r2';
    protected $description = 'Test Cloudflare R2 integration and signed URL generation';

    public function handle()
    {
        $this->info('Testing R2 connection...');

        try {
            $filename = 'test-file-' . time() . '.txt';
            $content = 'This is a test file for R2 signed URL verification.';
            
            // Write to R2
            Storage::disk('r2')->put($filename, $content);
            $this->info("Successfully wrote {$filename} to R2 bucket.");

            // Generate temporary URL
            $url = Storage::disk('r2')->temporaryUrl(
                $filename,
                Carbon::now()->addMinutes(15)
            );

            $this->info('Generated Signed URL (valid for 15 minutes):');
            $this->line($url);
            $this->newLine();
            $this->warn('Please click the URL above to verify it downloads successfully without x-amz-security-token signature errors.');

        } catch (\Exception $e) {
            $this->error('Failed to interact with R2:');
            $this->error($e->getMessage());
        }
    }
}
