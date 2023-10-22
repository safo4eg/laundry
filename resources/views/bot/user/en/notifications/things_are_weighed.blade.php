🎉Your things are clean and orderly, ready to be returned as soon as possible.
The courier will write to you in advance and specify a convenient delivery time.

<b>Total weight of washed items:</b>
@foreach($services_info['services'] as $service_info)
    {{$service_info['title']}} <b>{{$service_info['amount']}}</b> - <b>{{$service_info['price']}}</b> IDR
@endforeach
<b>Order price {{$services_info['total_price']}}</b>

How would it be more convenient for you to make payment?

‼️The courier will leave the items only after payment.

