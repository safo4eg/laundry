Here you can see the status of your active requests to our support team:

@if(!$tickets->isEmpty())
    @foreach($tickets as $ticket)
        <b>#{{ $ticket->id }}</b>
        <i>Access time: {{ $ticket->time_start }}</i>
    @endforeach
@else
    <i>You do not have archived requests</i>
@endif
