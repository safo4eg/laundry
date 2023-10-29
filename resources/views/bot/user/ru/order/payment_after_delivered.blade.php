Оплата заказа <b>#{{($order_services->first())->order->id}}</b>

<b>Общий вес постиранных вещей:</b>

@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> рупий
@endforeach

<b>Сумма заказа {{$price}}</b>

@if(isset($payment))
    Выбранный способ оплаты: <b>{{$payment['desc']}}</b>

    @if($payment['id'] !== 4)
        @if($payment['id'] === 1)
            💸Передайте данную сумму нашему курьеру или оставьте стаффу на ресепшене.У курьера всегда имеется с собой сдача.
        @elseif($payment['id'] === 2 OR $payment['id'] === 3)
            @if($payment['id'] === 2)
                💳 Вот данные для перевода на карту индонезийского банка BRI в рупиях:

                <b>4628 0100 4036 508</b>
                <b>Anak Agung Gede Adi Semara</b>
            @elseif($payment['id'] === 3)
                💳 Вот данные для перевода на карту Тинькофф в рублях:

                <b>2200 7007 7932 1818</b>
                <b>Olga G.</b>

                <b>Сумма для оплаты в рублях: {{$payment['ru_price']}}</b>
            @endif

            🧾 После того как переведёте, нажмите кнопку <b>"Отправить фото оплаты"</b> и отправьте скриншот перевода.
        @endif

        Для смены способа оплаты нажмите кнопку <b>"Изменить способ оплаты"</b>.

        ‼️Курьер оставит вещи только после оплаты.
    @else
        ✅Оплачено с помощью бонусов
    @endif
@else
    Для выбор способа оплаты нажмите кнопку <b>"Выбрать способ оплаты"</b>.
@endif

