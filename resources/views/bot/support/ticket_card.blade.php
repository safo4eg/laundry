<b>Ticket #{{$ticket->id}}</b>
<b>User</b>: {{ $user->username }} - ID{{ $user->id }}
@if(isset($user->orders))
    <b>Last order</b>
@endif

@foreach($ticket->ticketItems as $message)
    @if($message->chat_id !== \App\Models\Chat::where('name', 'Support')->first()->chat_id)
        <b>User</b> | <i>{{ $message->time }}</i>
    @else
        <b>Support</b> | <i>{{ $message->time }}</i>
    @endif
    {{ $message->text }}
    @if($message->file)
        @foreach($message->file as $file)
            <a href="{{ url($file->path) }}">The attachment</a>
        @endforeach
    @endif
@endforeach
