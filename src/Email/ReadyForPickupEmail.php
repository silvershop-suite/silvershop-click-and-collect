<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Email;

use Psr\Log\LoggerInterface;
use SilverShop\Extension\ShopConfigExtension;
use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use Throwable;

/**
 * Builds + sends the ready-for-pickup email: the customer is told their click & collect order is ready to be
 * picked up in store.
 *
 * Self-contained styling (its own `<style>` block, inline-safe) — it deliberately does NOT `<% include %>`
 * the core email shell, which needs silvershop/core 6.2.0, so the module stays installable on core 6.1.
 *
 * It stamps two headers so an optional `silvershop/notification-log` can record the send with zero coupling
 * (the log only READS headers):
 *   X-SilverShop-Order-ID: {OrderID}
 *   X-SilverShop-Notification-Key: pickup.ready
 */
class ReadyForPickupEmail
{
    use Configurable;

    /** Stable notification key — same convention as the notifications registry / log. */
    public const NOTIFICATION_KEY = 'pickup.ready';

    private static string $html_template = 'SilverShop/ClickAndCollect/Email/ReadyForPickupEmail';

    public static function create_for(Order $order): Email
    {
        $from = ShopConfigExtension::config()->get('email_from')
            ?: Email::config()->get('admin_email')
            ?: ('no-reply@' . self::senderHost());
        $to = (string) $order->getLatestEmail();

        $reference = $order->exists() ? (string) $order->Reference : '';
        $subject = _t(
            self::class . '.Subject',
            'Your order #{OrderNo} is ready to collect',
            '',
            ['OrderNo' => $reference]
        );

        $config = SiteConfig::current_site_config();
        $storeName = $config->hasMethod('StoreProfileName')
            ? (string) $config->StoreProfileName()
            : (string) $config->Title;
        $storeAddress = $config->hasMethod('StoreFormattedAddress')
            ? (string) $config->StoreFormattedAddress(true)
            : '';

        $template = (string) self::config()->get('html_template');

        $email = Email::create()
            ->setHTMLTemplate($template)
            ->setPlainTemplate($template . 'Plain')
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject);

        $email->setData([
            'Order' => $order,
            'StoreName' => $storeName,
            'StoreAddress' => $storeAddress,
            'BaseURL' => Director::absoluteBaseURL(),
        ]);

        $headers = $email->getHeaders();
        $headers->addTextHeader('X-SilverShop-Order-ID', (string) (int) $order->ID);
        $headers->addTextHeader('X-SilverShop-Notification-Key', self::NOTIFICATION_KEY);

        return $email;
    }

    /**
     * Build + send the ready-for-pickup email. Best-effort: a missing recipient is skipped (returns false) and
     * a transport failure is logged, never fatal — a failed notification must not break an order write.
     */
    public static function send_for(Order $order): bool
    {
        if (!$order->exists() || trim((string) $order->getLatestEmail()) === '') {
            return false;
        }

        // Building AND sending are best-effort: a misconfigured sender or transport must never break the
        // order write that triggered this (it runs from Order::onAfterWrite).
        try {
            self::create_for($order)->send();
        } catch (Throwable $throwable) {
            Injector::inst()->get(LoggerInterface::class)->error(
                'ReadyForPickupEmail: failed to send for order ' . (int) $order->ID . ': ' . $throwable->getMessage()
            );
            return false;
        }

        return true;
    }

    /**
     * Host for the synthetic no-reply fallback sender, used only when neither ShopConfig.email_from nor
     * Email.admin_email is configured (so setFrom never receives an empty address).
     */
    private static function senderHost(): string
    {
        $host = parse_url((string) Director::absoluteBaseURL(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
