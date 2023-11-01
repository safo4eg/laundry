<b>Appeal #{{ $ticket->id }}</b> 📝

@foreach($messages as $message)
    @if($message->chat_id !== \App\Models\Chat::where('name', 'Support')->first()->chat_id)
        <b>You</b>: {{ $message->time }}
    @else
        <b>Support</b>: {{ $message->time }}
    @endif
    {{ $message->text }}
    @if($message->file)
        @foreach($message->file as $file)
            <a href="{{ url($file->path) }}">Attachment</a>
        @endforeach
    @endif
@endforeach
