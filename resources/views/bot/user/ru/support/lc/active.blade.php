Тут Вы можете посмотреть статус своих активных обращений в нашу службу поддержки:

@if(!$tickets->isEmpty())
    @foreach($tickets as $ticket)
        <b>#{{ $ticket->id }}</b>
        <i>Время обращения: {{ $ticket->time_start }}</i>
    @endforeach
@else
    <i>У вас нет архивных обращений</i>
@endif
