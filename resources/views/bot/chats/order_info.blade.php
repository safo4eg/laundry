<b>Order #{{$order->id}}</b>
<b>Wishes:</b> {{isset($order->wishes)? $order->wishes: 'NULL'}}

User id: <b>{{$order->user->id}}</b>
User login: <b>{{"@".$order->user->username}}</b>
User orders count: <b>{{$order->user->orders()->count()}}</b>
Number: <b>{{$order->user->phone_number}}</b>
Whatsapp: <b>{{($order->user->whatsapp)? $order->user->whatsapp: 'Не указан'}}</b>
Location: <b><a href="https://www.google.com/maps/place/{{$order->geo}}">{{$order->address}}</a></b>
Place: <b>{{$order->address_desc}}</b>

@foreach(($order->statuses) as $status)
    {{ $status->en_desc }}: {{ $status->pivot->created_at }}
@endforeach



