<?php

namespace Namu\WireChat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Namu\WireChat\Events\MessageCreated;
use Namu\WireChat\Events\NotifyParticipant;
use Namu\WireChat\Models\Conversation;
use Namu\WireChat\Models\Message;
use Namu\WireChat\Models\Participant;

class NotifyParticipantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected  $auth;

    protected $messagesTable;
    protected $participantsTable;



    public function __construct(public Model $conversation, public Message $message)
    {
        //  
        $this->onQueue(config('wirechat.broadcasting.message_notification_queue', 'default'));
        $this->auth = $message->sendable;


        #Get table
        $this->messagesTable = (new Message())->getTable();
        $this->participantsTable = (new Participant())->getTable();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Chunk the participants to avoid memory overload
        // $this->conversation->participants()
        //     ->with('participantable')
        //     ->where("$this->participantsTable.participantable_id", '!=', $this->sender->id)
        //     ->where("$this->participantsTable.participantable_type", get_class($this->sender))
        //     ->leftJoin("$this->messagesTable", function ($join) {
        //         $join->on("$this->participantsTable.participantable_id", '=', "$this->messagesTable.sendable_id")
        //             ->on("$this->participantsTable.participantable_type", '=', "$this->messagesTable.sendable_type")
        //             ->where("$this->messagesTable.conversation_id", $this->conversation->id);
        //     })
        //     ->select("$this->participantsTable.*", DB::raw("MAX($this->messagesTable.created_at) as last_message_time")) // Get last message time
        //     ->groupBy("$this->participantsTable.id") // Group by participants
        //     ->orderByDesc('last_message_time') // Order by the most recent message
        //     ->chunk(10, function ($participants) {
        //         // Loop through participants in batches of 100
        //         foreach ($participants as $participant) {
        //             broadcast(new NotifyParticipant($participant->participantable, $this->message));
        //         }
        //     });

        // $participants = $this->conversation->participants()
        // ->with('participantable')
        // ->where("$this->participantsTable.participantable_id", '!=', $this->auth->id)
        // ->where("$this->participantsTable.participantable_type", get_class($this->auth))
        // ->leftJoin("$this->messagesTable", function ($join) {
        //     $join->on("$this->participantsTable.participantable_id", '=', "$this->messagesTable.sendable_id")
        //         ->on("$this->participantsTable.participantable_type", '=', "$this->messagesTable.sendable_type")
        //         ->where("$this->messagesTable.conversation_id", $this->conversation->id);
        // })
        // ->select("$this->participantsTable.*", DB::raw("MAX($this->messagesTable.created_at) as last_message_time")) // Get last message time
        // ->groupBy("$this->participantsTable.id") // Group by participants
        // ->orderByDesc('last_message_time') // Order by the most recent message
        // ->get();

             //Get conversation participants except auth
            //  $participants = $this->conversation->participants()
            //  ->where('participantable_id','!=',$this->auth->id)
            //  ->where('participantable_type',get_class($this->auth))
            //   ->chunk(500, function ($participants) {
            //             // Loop through participants in batches of 100
            //             foreach ($participants as $participant) {
            //                 broadcast(new NotifyParticipant($participant->participantable, $this->message));
            //             }
            //         });;


            $participants = $this->conversation->participants()
            ->with('participantable')
            ->where('participantable_id', '!=', $this->auth->id)
            ->chunk(100, function ($chunkedParticipants) {
                // Prepare the jobs for the chunk
                $jobs = [];
        
                foreach ($chunkedParticipants as $participant) {
                    $jobs[] = new NotifySingleParticipantJob($participant, $this->message);
                }
        
                // Dispatch the batch of jobs
                Bus::batch($jobs)->dispatch();
            });

                    
        



        // // Loop through users
        // foreach ($participants as $participant) {
        //     broadcast(new NotifyParticipant($participant->participantable,$this->message));
        // }
    }
    
}
