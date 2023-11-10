<b>Your bonus balance is {{$balance}} IDR</b>

Order price (<b>#{{$order->id}}:</b> @price($order->price)
@if((int)$balance >= (int)$order->price)
    You can pay for your order in full with bonuses

    <b>The balance due will be 0 IDR</b>
@else
    You can pay with bonuses @price($balance)
@endif

Do you confirm the debiting of bonuses for this order?
