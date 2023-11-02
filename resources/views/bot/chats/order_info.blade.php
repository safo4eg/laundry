<b>Order #{{$order->id}}</b>
@if($order->status_id === 2)
    Status: <b>Ready to be sent to the courier</b>
@elseif($order->status_id === 3)
    Status: <b>Sent to courier</b>
@endif
Wishes: {{isset($order->wishes)? $order->wishes: 'NULL'}}

User id: <b>{{$order->user->id}}</b>
User login: <b>{{"@".$order->user->username}}</b>
User orders count: <b>{{$order->user->orders()->count()}}</b>
Number: <b>{{$order->user->phone_number}}</b>
Whatsapp: <b>{{($order->user->whatsapp)? $order->user->whatsapp: 'Не указан'}}</b>
Location: <b>{{$order->address}}</b>
Place: <b>{{$order->address_desc}}</b>
