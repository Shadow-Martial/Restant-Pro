<?php

namespace App\Events;

use App\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The order instance.
     *
     * @var \App\Order
     */
    public $order;

    public $msg;

    public $owner;

    public $status;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($order, $msg,$owner, $status)
    { 
        $this->order = $order;
        $this->msg = $msg;
        $this->owner = $owner;
        $this->status = $status;
    }

   /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('order.'.$this->order['id']),   
        ];
    }

    public function broadcastAs()
    {
        return 'updateorder-event';
    }
}
