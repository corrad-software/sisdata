<?php

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sisdata:bucket', function () {
    $disk = Storage::disk('dokumen');

    if (! $disk instanceof AwsS3V3Adapter) {
        $this->error('The dokumen disk is not an S3 disk.');

        return 1;
    }

    $client = $disk->getClient();
    $bucket = config('filesystems.disks.dokumen.bucket');

    if (! $client->doesBucketExist($bucket)) {
        $client->createBucket(['Bucket' => $bucket]);
    }

    $this->info("Bucket {$bucket} ready.");
})->purpose('Create the MOD-DMS document bucket on the dokumen disk (MinIO locally)');
