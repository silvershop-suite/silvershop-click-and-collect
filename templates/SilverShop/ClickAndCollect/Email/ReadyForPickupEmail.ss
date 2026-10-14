<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Heading "Your order is ready to collect" %></title>
    <style>
        body { margin: 0; padding: 0; background: #f5f6f8; color: #1c2733;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .shell { max-width: 600px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #ffffff; border: 1px solid #e3e7ec; border-radius: 12px; padding: 28px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { margin: 0 0 14px; font-size: 15px; line-height: 1.5; }
        .ref { font-weight: 600; font-size: 16px; }
        .muted { color: #5b6b7b; font-size: 13px; }
        .location { background: #f0f5ff; border: 1px solid #cfe0ff; border-radius: 10px; padding: 14px; font-size: 14px; margin: 14px 0; }
        .location strong { display: block; margin-bottom: 4px; }
        .note { background: #fff8ec; border: 1px solid #f3dcae; border-radius: 10px; padding: 14px; font-size: 14px; }
        .fineprint { color: #8a97a4; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <h1><%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Heading "Your order is ready to collect" %></h1>

        <p><%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Intro "Good news — your order is ready to be picked up in store." %></p>

        <% if $Order %>
            <p class="ref"><%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Order "Order" %> #$Order.Reference</p>
        <% end_if %>

        <% if $StoreAddress %>
            <div class="location">
                <% if $StoreName %><strong>$StoreName</strong><% end_if %>
                $StoreAddress.RAW
            </div>
        <% end_if %>

        <div class="note">
            <%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.BringReference "Please bring your order reference (and ID if we ask for it) when you collect." %>
        </div>

        <p class="fineprint"><%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Fineprint "You are receiving this because you chose to collect your order in store." %></p>
    </div>
</div>
</body>
</html>
