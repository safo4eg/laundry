<b>Order #{{$order->id}}</b>

User id: <b>{{$order->user->id}}</b>
User login: <b>{{"@".$order->user->username}}</b>
Number: <b>{{$order->user->phone_number}}</b>
Whatsapp: <b>{{($order->user->whatsapp)? $order->user->whatsapp: 'Не указан'}}</b>
Location: <b>{{$order->address}}</b>
Place: <b>{{$order->address_desc}}</b>
