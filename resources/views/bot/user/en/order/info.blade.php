<b>Order #{{$order->id}} </b>

<b>Date of creation</b> {{$status_1->pivot->created_at}}
@if($order->status_id === 1 OR $order->status_id === 2)
    @if($order->status_id === 2)
        <b>Status:</b> Waiting for the courier
    @elseif($order->status_id === 3)
        <b>Status:</b> Sent to courier
    @endif
    <b>Waiting to be filled: </b> {{isset($order->wishes)? $order->wishes: 'Null'}}

    📌The courier will write you in advance about the time of arrival.
    📌 Send Laundry Special Requests, if any.
    📌For your convenience, you can simply leave things at the reception or your STAFF.
    📌Payment for the order occurs before the return of the washed items.

    ❗️Orders placed <b>before 2 pm</b> will be picked up within 2 hours and returned back tomorrow afternoon.
    ❗️Orders placed <b>after 2 pm</b> will be picked up tonight and returned back tomorrow evening.
    ❗️Orders placed <b>after 6 pm</b> will be picked up before lunch tomorrow and returned back the day after tomorrow.

@elseif($order->status_id === 4 AND $order->reason_id !== 5)
    <b>Status:</b> Canceled
    <b>Reason: </b> {{$order->reason->ru_desc}}
@endif
