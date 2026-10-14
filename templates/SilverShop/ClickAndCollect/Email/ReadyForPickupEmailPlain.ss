<%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Heading "Your order is ready to collect" %>

<%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Intro "Good news — your order is ready to be picked up in store." %>
<% if $Order %>
<%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Order "Order" %> #$Order.Reference
<% end_if %>
<% if $StoreName %>
$StoreName
<% end_if %>
<%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.BringReference "Please bring your order reference (and ID if we ask for it) when you collect." %>

<%t SilverShop\ClickAndCollect\Email\ReadyForPickupEmail.Fineprint "You are receiving this because you chose to collect your order in store." %>
