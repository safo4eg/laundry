Order <b>#{{$order->id}}</b> is confirmed.

@if(!isset($order->rating))
    🤩 Rate the quality of the streak from 1 to 5.
@elseif(in_array($order->rating, [1, 2, 3]))
    😭 We are sorry that you were not pleased with the quality of your order.
    👌 We will try to take into account all your wishes in the next order.
@elseif(in_array($order->rating, [4, 5]))
    🥳 Thank you for your high rating of your order!
@endif

🤝<b>Tell your friends about us.</b>
EVERY TIME your friends wash their clothes with us, you will receive 10% cashback on their order amount.

❗️We remind you that claims regarding the quality of washing and the integrity of the order are accepted within 24 hours after receiving the order back.
