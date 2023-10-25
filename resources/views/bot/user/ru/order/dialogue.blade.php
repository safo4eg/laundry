Чат с курьером заказа <b>#{{$order->id}}</b>
@if($order_messages->isEmpty())
    <b>Пусто.</b>
    Чтобы связаться с курьером нажмите кнопку "Написать"
@else
    @foreach($order_messages as $order_message)
        @if($order_message->sender_chat_id == $current_chat_id)
            <b>Вы:</b>
        @else
            <b>Курьер:</b>
        @endif
            {{$order_message->text}} <i>{{$order_message->created_at}}</i>
    @endforeach
@endif
