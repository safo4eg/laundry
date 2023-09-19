Order #{{$order->id}}

User id: {{$order->user->id}}
User login: {{$order->user->username}}
Number: {{$order->user->phone_number}}
Whatsapp: {{($order->user->whatsapp)? $order->user->whatsapp: 'Не указан'}}
Location: {{$order->address}}
Place: {{$order->address_desc}}

