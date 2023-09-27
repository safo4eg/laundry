<b>Заказ #{{$order->id}} </b>

<b>Дата создания:</b> {{$status_1->pivot->created_at}}
@if($order->status_id === 2 OR $order->status_id === 3)
    @if($order->status_id === 2)
        <b>Статус:</b> Ожидает назначения курьера
    @elseif($order->status_id === 3)
        <b>Статус:</b> Отправлен курьеру
    @endif
    <b>Пожелания по стирке: </b> {{isset($order->wishes)? $order->wishes: 'Не указано'}}

    📌Курьер заблаговременно напишет Вам о времени прибытия.
    📌Отправьте Особые пожелания по стирке, если такие имеются.
    📌Для вашего удобства Вы можете просто оставить вещи на ресепшене или вашему СТАФФУ.
    📌Оплата заказа происходит перед возвратом стиранных вещей.

    ❗️Заказ, сделанный <b>до 14:00</b>, заберём в течение 2-х часов и вернём обратно завтра днём.
    ❗️Заказ, сделанный <b>после 14:00</b>, заберём сегодня вечером и вернём обратно завтра вечером.
    ❗️Заказ, сделанный <b>после 18:00</b>, заберём завтра до обеда и вернём обратно послезавтра днём.

@elseif($order->status_id === 4 AND $order->reason_id !== 5)
    <b>Статус:</b> Отменён
    <b>Причина: </b> {{$order->reason->ru_desc}}
@endif
