<b>Обращение #{{ $ticket->id }}</b> 📝

@foreach($messages as $message)
    @if($message->chat_id !== \App\Models\Chat::where('name', 'Support')->first()->chat_id)
        <b>Вы</b>: {{ $message->time }}
    @else
        <b>Поддержка</b>: {{ $message->time }}
    @endif
    {{ $message->text }}
    @if($message->file)
        @foreach($message->file as $file)
            <a href="{{ url($file->path) }}">Вложение</a>
        @endforeach
    @endif

@endforeach
