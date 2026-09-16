<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class RiderInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $activationUrl;

    public function __construct(User $user, string $activationUrl)
    {
        $this->user = $user;
        $this->activationUrl = $activationUrl;
    }

    public function build()
    {
        return $this
            ->subject('You have been invited to become an OrderMe Rider')
            ->view('emails.rider-invitation');
    }
}