<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NairaTransactionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $amount;
    public $debitAccountName;
    public $balance;

    /**
     * Create a new message instance.
     *
     * @param string $username
     * @param float $amount
     * @param string $debitAccountName
     * @param float $balance
     */
    public function __construct($username, $amount, $debitAccountName, $balance)
    {
        $this->username = $username;
        $this->amount = $amount;
        $this->debitAccountName = $debitAccountName;
        $this->balance = $balance;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Credit Alert')
                    ->view('emails.naira_transaction_notification');
    }
}
