Обращение #{{ $ticket->id }}

@foreach($messages as $message)
    @if($message->chat_id !== \App\Models\Chat::where('name', 'Support')->first()->id)
        <b>You</b> <i>{{ $message->time }}</i>
    @else
        <b>Support</b> <i>{{ $message->time }}</i>
    @endif
    {{ $message->text }}
    @if($message->file)
        @foreach($message->file as $file)
            <a href="{{ url($file->path) }}">Вложение</a>
        @endforeach
    @endif
@endforeach
