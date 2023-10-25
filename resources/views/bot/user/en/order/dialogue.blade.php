Chat with the order courier <b>#{{$order->id}}</b>
@if($order_messages->isEmpty())
    <b>Empty.</b>
    To contact the courier, click the “Write” button
@else
    @foreach($order_messages as $order_message)
        @if($order_message->sender_chat_id == $current_chat_id)
            <b>You:</b>
        @else
            <b>Courier:</b>
        @endif
            {{$order_message->text}} <i>{{$order_message->created_at}}</i>
    @endforeach
@endif
