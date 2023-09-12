<?php

namespace App\Jobs;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadFileSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filename;

    /**
     * Create a new job instance.
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $credentials = new Credentials($_ENV['AWS_ACCESS_KEY_ID'], $_ENV['AWS_SECRET_ACCESS_KEY']);

        $s3 = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => "https://" . config('filesystems.disks.s3.account') . "." . "r2.cloudflarestorage.com",
            'credentials' => $credentials
        ]);

        $key = "userfiles/file_submission/" . $this->filename;
        
        $s3->putObject([
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $key,
            'Body' => file_get_contents(public_path('storage/pdf_queue/' . $this->filename)),
            'ACL'    => 'public-read',
        ]);

        $filePath = 'public/pdf_queue/' . $this->filename;

        Storage::delete($filePath);
    }
}
