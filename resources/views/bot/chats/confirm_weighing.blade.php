@foreach($price['services'] as $service_info)

    {{$service_info['title']}}: <b>{{$service_info['amount']}}</b>
    Price: <b>{{$service_info['price']}}IRD</b>
@endforeach

Order price: <b>{{$price['sum']}}IDR</b>
