Here you can see the history of calls to our support service:

@if(!$tickets->isEmpty())
    @foreach($tickets as $ticket)
        <b>#{{ $ticket->id }}</b>
        <i>Request time: {{$ticket->time_start }}</i>
    @endforeach
@else
    <i>You have no archived requests</i>
@endif
