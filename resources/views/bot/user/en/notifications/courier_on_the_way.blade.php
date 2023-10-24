🎉Your things are clean and orderly, the courier is already on its way to you.

<b>Total weight of washed items:</b>
@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> рупий
@endforeach
<b>Total price: {{$price}}</b>

Select a payment method or contact the courier to clarify delivery time.

‼️The courier will leave the items only after payment.

