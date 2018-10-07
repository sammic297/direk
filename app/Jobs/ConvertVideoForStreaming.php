<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use FFMpeg;
use App\Video;
use Carbon\Carbon;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;

class ConvertVideoForStreaming implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // create a video format...
            $lowBitrateFormat = (new X264('libmp3lame', 'libx264'))->setKiloBitrate(500);

            $converted_name = $this->getCleanFileName($this->video->path);

            // open the uploaded video from the right disk...
            FFMpeg::fromDisk($this->video->disk)
                ->open($this->video->path)

                // add the 'resize' filter...
                ->addFilter(function ($filters) {
                    $filters->resize(new Dimension(960, 540));
                })

                // call the 'export' method...
                ->export()

                // tell the MediaExporter to which disk and in which format we want to export...
                ->toDisk('public')
                ->inFormat($lowBitrateFormat)

                // call the 'save' method with a filename...
                ->save($converted_name);

            // update the database so we know the convertion is done!
            $this->video->update([
                'converted_for_streaming_at' => Carbon::now(),
                'processed' => true,
                'stream_path' => $converted_name,
            ]);
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }

    private function getCleanFileName($filename)
    {
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename).'.mp4';
    }
}
