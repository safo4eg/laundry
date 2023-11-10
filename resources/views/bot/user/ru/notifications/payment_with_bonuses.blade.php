<b>Ваш бонусный баланс равен {{$balance}} IDR</b>

Сумма заказа (<b>#{{$order->id}}</b>): @price($order->price)

@if((int)$balance >= (int)$order->price)
    Вы можете полностью оплатить заказ бонусами

    <b>Остаток к оплате будет 0 IDR</b>
@else
    Вы можете оплатить бонусами @price(isset($balance)? $balance: 0) IDR

    <b>Остаток к оплате будет: @price($order->price - $balance)</b>
@endif

Подтверждаете списание бонусов за данный заказ?
