<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pusher\PushNotifications\PushNotifications; 

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $content;
    protected $id_user;
    protected $notif_from;
    protected $url;
    protected $url_mobile;
    protected $tab; 
    protected $section; 
    protected $additional_data;

    /**
     * Create a new job instance.
     */
    public function __construct($content, $id_user, $notif_from, $url, $url_mobile, $tab, $section, $additional_data)
    {
        $this->content = $content;
        $this->id_user = $id_user;
        $this->notif_from = $notif_from;
        $this->url = $url;
        $this->url_mobile = $url_mobile;
        $this->tab = $tab;
        $this->section = $section;
        $this->additional_data = $additional_data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $beamsClient = new PushNotifications([
            "instanceId" => env('PUSHER_APP_ID'),
            "secretKey" => env('PUSHER_APP_KEY'),
        ]);

        $pusherData = [
            //android
            "fcm" => [
                "notification" => [
                    "title" => "Notification",
                    "body" => $this->content,
                ],
                "data" => [
                    "deep_link" => $this->url_mobile
                ]
            ],
            //ios
            "apns" => [
                "aps" => [
                    "alert" => [
                      "title" => "Notification",
                      "body" => $this->content
                    ],
                    "data" => [
                        "url" => $this->url_mobile
                    ]
                ]
            ],
            //web
            "web" => [
                "notification" => [
                    "title" => "Notification",
                    "body" => $this->content,
                    "deep_link" => $this->url
                ]
            ]
        ];

        $publishResponse = $beamsClient->publishToUsers([env('PUSHER_PREFIX') . '-' . $this->id_user], $pusherData);

        if ($publishResponse->getStatusCode() === 200) {
            echo("Published with Publish ID: " . $publishResponse->publishId);
        } else {
            echo("An error occurred while sending the notification: " . $publishResponse->getStatusCode());
        }
    }
}
