✍️To add text that will be in the notification, click "Add text"

➡️Select the necessary buttons that will be in the notification:
⏹“Start button” - start filling out the order,
⏹“Recommend button” - button for sending a referral link to contacts

@if(isset($notification['text_ru']))
    <b>Текст уведомления на русском:</b>
    {{$notification['text_ru']}}
@endif
@if(isset($notification['text_en']))
    <b>Notification text in English:</b>
    {{$notification['text_en']}}
@endif
