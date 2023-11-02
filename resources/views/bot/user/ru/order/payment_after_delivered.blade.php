Оплата заказа <b>#{{($order_services->first())->order->id}}</b>

<b>Общий вес постиранных вещей:</b>

@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> рупий
@endforeach

<b>Сумма заказа {{$price}}</b>

@if(!is_null($payment['method_id']))
    @if($payment['status_id'] !== 3)
        Выбранный способ оплаты: <b>{{$payment['desc']}}</b>
        @if($payment['method_id'] === 1)
            💸Передайте данную сумму нашему курьеру или оставьте стаффу на ресепшене.У курьера всегда имеется с собой сдача.

            Для смены способа оплаты нажмите кнопку <b>"Изменить способ оплаты"</b>.
        @elseif($payment['method_id'] === 2 OR $payment['method_id'] === 3)
            @if($payment['status_id'] === 2)
                ✅ После успешного подтверждения платежа Вам придет уведомление.
            @elseif($payment['status_id'] === 1)
                @if($payment['method_id'] === 2)
                    💳 Вот данные для перевода на карту индонезийского банка BRI в рупиях:

                    <b>4628 0100 4036 508</b>
                    <b>Anak Agung Gede Adi Semara</b>
                @elseif($payment['method_id'] === 3)
                    💳 Вот данные для перевода на карту Тинькофф в рублях:

                    <b>2200 7007 7932 1818</b>
                    <b>Olga G.</b>

                    <b>Сумма для оплаты в рублях: {{$payment['ru_price']}}</b>
                @endif

                🧾 После того как переведёте, нажмите кнопку <b>"Отправить фото оплаты"</b> и отправьте скриншот перевода.

                ‼️Курьер оставит вещи только после оплаты.

                Для смены способа оплаты нажмите кнопку <b>"Изменить способ оплаты"</b>.
            @endif
        @endif
    @else
        @if($payment['method_id'] ===  4)
            ✅Оплачено с помощью бонусов
        @endif
    @endif
@else
    Для выбора способа оплаты нажмите кнопку <b>"Выбрать способ оплаты"</b>.
@endif

