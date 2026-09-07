<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\TopUp;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/** Money reached the balance. */
class TopUpConfirmedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly string $reference,
        private readonly int $amountCents,
        private readonly int $bonusCents,
        private readonly string $method = 'card',
    ) {
        parent::__construct();
    }

    public static function for(TopUp $topUp): self
    {
        return new self(
            $topUp->reference,
            $topUp->amount_cents,
            $topUp->bonus_cents,
            mb_strtolower($topUp->method->label()),
        );
    }

    public function type(): NotificationType
    {
        return NotificationType::TopUpConfirmed;
    }

    public function title(): string
    {
        return (new Money($this->amountCents + $this->bonusCents))->format().' added to your balance';
    }

    public function body(): string
    {
        return $this->bonusCents > 0
            ? sprintf(
                '%s paid, plus a %s volume bonus. Reference %s.',
                (new Money($this->amountCents))->format(),
                (new Money($this->bonusCents))->format(),
                $this->reference,
            )
            : "Reference {$this->reference}. It is available to spend now.";
    }

    public function href(): string
    {
        return '/balance?tab=transactions';
    }

    protected function action(): string
    {
        return 'See your balance';
    }

    /**
     * A receipt, not a notice.
     *
     * Somebody reconciling a card statement three weeks later needs the figure
     * paid, the credit added, and the reference — and the sentence about frozen
     * versus spent, which is what advertisers ask support about most.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = new Money($this->amountCents);
        $bonus = new Money($this->bonusCents);

        $mail = (new MailMessage)
            ->subject("Payment received — {$amount->format()}")
            ->line("We have added {$amount->format()} to your Publinza balance, paid by {$this->method}.");

        if ($bonus->isPositive()) {
            $mail->line(sprintf(
                'Your volume bonus of %s has been credited on top, so %s reached your balance.',
                $bonus->format(),
                (new Money($this->amountCents + $this->bonusCents))->format(),
            ));
        }

        return $mail
            ->line("Reference {$this->reference}.")
            ->line('Your balance is money you hold with us. It is only spent when a placement goes live and its link is verified.')
            ->action('View your balance', $this->url('/balance'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'reference' => $this->reference,
            'amount_cents' => $this->amountCents,
            'bonus_cents' => $this->bonusCents,
        ];
    }
}
