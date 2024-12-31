<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransactionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $amount;
    public $currency;
    public $transactionId;
    public $type;
    public $greeting; // Add greeting variable

    /**
     * Create a new message instance.
     *
     * @param string $name
     * @param float $amount
     * @param string $currency
     * @param string $transactionId
     * @param string $type
     */
    public function __construct($name, $amount, $currency, $transactionId, $type)
    {
        $this->name = $name;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->transactionId = $transactionId;
        $this->type = $type;
        
        // Set the greeting message based on the transaction type
        $this->greeting = $this->type === 'received' ? "Hello $this->name, your transaction has been received!" : "Hello $this->name, your transaction was successful!";
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = $this->type === 'received' ? 'Transfer Received' : 'Transfer Successful';

        return $this->subject($subject)
                    ->view('emails.transaction');
    }
}
