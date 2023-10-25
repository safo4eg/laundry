<b>Ticket #{{$ticket->id}}</b>

@foreach($ticket->ticketItems as $message)
    @if($message->chat_id !== \App\Models\Chat::where('name', 'Support')->first()->id)
        <b>{{"@".$ticket->user->username}}</b> <i>{{ $message->time }}</i>
    @else
        <b>Support</b> <i>{{ $message->time }}</i>
    @endif
    {{ $message->text }}
    @if($message->file)
        @foreach($message->file as $file)
            Вложение
        @endforeach
    @endif
@endforeach
