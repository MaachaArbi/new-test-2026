<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Translation;

use App\Modules\Booking\Domain\Exception\BookingPayerSplitAlreadyActiveException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitCurrencyMismatchException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitExceedsTotalException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitNotFoundException;
use App\Modules\Booking\Domain\Exception\InvalidBookingPayerSplitException;
use App\Modules\Booking\Domain\Exception\BookingSettlementAlreadyActiveException;
use App\Modules\Booking\Domain\Exception\BookingSettlementNotFoundException;
use App\Modules\Booking\Domain\Exception\InvalidBookingSettlementException;
use App\Modules\Booking\Domain\Exception\InvalidSettlementRateException;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyAlreadyExistsException;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyNotFoundException;
use App\Modules\Booking\Domain\Exception\BookingCancellationRoomMismatchException;
use App\Modules\Booking\Domain\Exception\BookingChargeSegmentMismatchException;
use App\Modules\Booking\Domain\Exception\BookingChargeTravelerMismatchException;
use App\Modules\Booking\Domain\Exception\BookingUnknownChannelException;
use App\Modules\Booking\Domain\Exception\BookingUnknownChargeTypeException;
use App\Modules\Booking\Domain\Exception\BookingUnknownCurrencyException;
use App\Modules\Booking\Domain\Exception\BookingUnknownServiceTypeException;
use App\Modules\Booking\Domain\Exception\BookingUnknownStatusException;
use App\Modules\Booking\Domain\Exception\BookingFolderReferenceCodeAlreadyUsedException;
use App\Modules\Booking\Domain\Exception\BookingNoChangesException;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use App\Modules\Booking\Domain\Exception\BookingStatusUnchangedException;
use App\Modules\Booking\Domain\Exception\BookingTravelerPaxLeaderAlreadySetException;
use App\Modules\Booking\Domain\Exception\InvalidBookingCancellationTierException;
use App\Modules\Booking\Domain\Exception\InvalidBookingCarRentalDetailException;
use App\Modules\Booking\Domain\Exception\InvalidBookingStateException;
use App\Modules\Booking\Domain\Exception\InvalidBookingTransportSegmentException;
use App\Modules\CashManagement\Domain\Exception\CashPaymentMethodRoutingAlreadyExistsException;
use App\Modules\CashManagement\Domain\Exception\CashPaymentMethodRoutingNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashRoutingTypeNotFoundException;
use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\Core\Domain\Exception\InvalidCoreCredentialStateException;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountStateException;
use App\Modules\Party\Domain\Exception\PartyAccountFunctionAssignmentNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountGroupMembershipNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountNoChangesException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountRoleAssignmentNotFoundException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentPartyRoleException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentStatusException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementLedgerEntryException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementMatchingException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementTransferAmountException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementTransferPartyRoleException;
use App\Modules\Reglements\Domain\Exception\ReglementEntryTypeNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotActiveException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentStatusUnchangedException;
use App\Modules\Reglements\Domain\Exception\ReglementLedgerEntryNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingBookMismatchException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsCreditException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsDebitException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementPaymentMethodInactiveException;
use App\Modules\Reglements\Domain\Exception\ReglementTransferPostingFailedException;
use App\Modules\Reglements\Domain\Exception\ReglementUnknownCurrencyException;
use App\Shared\Domain\Exception\CurrencyMismatchException;
use App\Shared\Domain\Exception\InvalidCurrencyCodeException;
use App\Shared\Domain\Exception\InvalidEmailException;
use App\Shared\Domain\Exception\InvalidExchangeRateException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Vérifie que chaque errorCode() Domain existant a une entrée dans le domain
 * « errors » pour en / fr / ar — sans listener HTTP.
 */
final class ErrorsTranslationCatalogueTest extends KernelTestCase
{
    private const LOCALES = ['en', 'fr', 'ar'];

    private const DOMAIN = 'errors';

    public function test_every_domain_error_code_resolves_in_all_configured_locales(): void
    {
        self::bootKernel();

        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        foreach ($this->existingDomainErrorCodes() as $errorCode) {
            foreach (self::LOCALES as $locale) {
                $translated = $translator->trans($errorCode, [], self::DOMAIN, $locale);

                self::assertNotSame(
                    $errorCode,
                    $translated,
                    sprintf('Missing translation for "%s" in locale "%s" (domain "%s").', $errorCode, $locale, self::DOMAIN),
                );
                self::assertNotSame('', trim($translated));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function existingDomainErrorCodes(): array
    {
        return [
            InvalidEmailException::invalidFormat('not-an-email')->errorCode(),
            CurrencyMismatchException::cannotAdd('TND', 'EUR')->errorCode(),
            InvalidCurrencyCodeException::invalidFormat('TN')->errorCode(),
            InvalidExchangeRateException::invalidFormat('nope')->errorCode(),
            InvalidPartyAccountStateException::parentAccountNotAllowedForPerson(1, 'Alice')->errorCode(),
            PartyAccountNotFoundException::forId(999_999_999)->errorCode(),
            PartyAccountNoChangesException::create()->errorCode(),
            PartyAccountRoleAssignmentNotFoundException::forId(999_999_992)->errorCode(),
            PartyAccountFunctionAssignmentNotFoundException::forId(999_999_993)->errorCode(),
            PartyAccountGroupMembershipNotFoundException::forId(999_999_994)->errorCode(),
            InvalidCoreCredentialStateException::oauthProviderCannotBeLocal(1)->errorCode(),
            BookingFolderReferenceCodeAlreadyUsedException::forCode('DOS-X')->errorCode(),
            BookingUnknownServiceTypeException::forCode('x')->errorCode(),
            BookingUnknownStatusException::forCode('x')->errorCode(),
            BookingUnknownChannelException::forCode('x')->errorCode(),
            BookingUnknownCurrencyException::forCode('achatCurrencyCode', 'ZZZ')->errorCode(),
            BookingUnknownChargeTypeException::forCode('x')->errorCode(),
            BookingChargeTravelerMismatchException::forBookingAndTraveler(1, 2)->errorCode(),
            BookingChargeSegmentMismatchException::forBookingAndSegment(1, 2)->errorCode(),
            BookingSettlementAlreadyActiveException::forTriplet(1, 'distributeur', 2)->errorCode(),
            BookingSettlementNotFoundException::forId(999_999_989)->errorCode(),
            InvalidBookingSettlementException::alreadyRevoked(1)->errorCode(),
            InvalidSettlementRateException::invalidFormat('nope')->errorCode(),
            BookingPayerSplitExceedsTotalException::forBooking(1, 50, 60, 100)->errorCode(),
            BookingPayerSplitCurrencyMismatchException::forBooking(1, 'TND', 'EUR')->errorCode(),
            BookingPayerSplitNotFoundException::forId(999_999_988)->errorCode(),
            InvalidBookingPayerSplitException::alreadyRevoked(1)->errorCode(),
            BookingPayerSplitAlreadyActiveException::forBookingAndPayer(1, 2)->errorCode(),
            BookingCancellationPolicyNotFoundException::forId(999_999_990)->errorCode(),
            BookingCancellationPolicyAlreadyExistsException::forBooking(1)->errorCode(),
            BookingCancellationRoomMismatchException::forBookingAndRoom(1, 2)->errorCode(),
            BookingNotFoundException::forId(999_999_991)->errorCode(),
            BookingNoChangesException::create()->errorCode(),
            BookingStatusUnchangedException::forStatus('confirmed')->errorCode(),
            BookingServiceTypeMismatchException::forBooking(1, 'hotel', 'flight')->errorCode(),
            BookingTravelerPaxLeaderAlreadySetException::forBooking(1)->errorCode(),
            InvalidBookingTransportSegmentException::arrivalBeforeDeparture(
                new \DateTimeImmutable('2026-11-01 18:00:00'),
                new \DateTimeImmutable('2026-11-01 10:00:00'),
            )->errorCode(),
            InvalidBookingCarRentalDetailException::dropoffBeforePickup(
                new \DateTimeImmutable('2026-06-25 15:00:00'),
                new \DateTimeImmutable('2026-06-25 10:00:00'),
            )->errorCode(),
            InvalidBookingCancellationTierException::percentageOutOfRange('150')->errorCode(),
            InvalidBookingStateException::endDateBeforeStartDate(
                new \DateTimeImmutable('2026-08-10'),
                new \DateTimeImmutable('2026-08-01'),
            )->errorCode(),
            InvalidReglementInstrumentException::amountMustBePositive(0)->errorCode(),
            ReglementInstrumentNotFoundException::forId(999_999_987)->errorCode(),
            ReglementInstrumentStatusUnchangedException::forStatus('active')->errorCode(),
            ReglementPaymentMethodInactiveException::forId(999_999_986)->errorCode(),
            ReglementUnknownCurrencyException::forCode('ZZZ')->errorCode(),
            InvalidReglementLedgerEntryException::amountMustBeNonZero(0)->errorCode(),
            ReglementEntryTypeNotFoundException::forCode('missing')->errorCode(),
            InvalidReglementTransferAmountException::amountMustBePositive(0)->errorCode(),
            InvalidReglementTransferPartyRoleException::forValue('nope')->errorCode(),
            ReglementTransferPostingFailedException::emptyResult()->errorCode(),
            InvalidReglementInstrumentPartyRoleException::forValue('nope')->errorCode(),
            InvalidReglementInstrumentStatusException::forValue('nope')->errorCode(),
            ReglementInstrumentNotActiveException::forId(1, 'cancelled')->errorCode(),
            ReglementLedgerEntryNotFoundException::forId(999_999_980)->errorCode(),
            InvalidReglementMatchingException::amountMustBePositive(0)->errorCode(),
            ReglementMatchingNotFoundException::forId(999_999_979)->errorCode(),
            ReglementMatchingBookMismatchException::forEntries(1, 2)->errorCode(),
            ReglementMatchingExceedsCreditException::forCredit(1, 100, 50, 60)->errorCode(),
            ReglementMatchingExceedsDebitException::forDebit(1, 100, 50, 60)->errorCode(),
            CashRoutingTypeNotFoundException::forCode('missing')->errorCode(),
            CashPaymentMethodRoutingNotFoundException::forPaymentMethodId(999_999_978)->errorCode(),
            CashPaymentMethodRoutingAlreadyExistsException::forPaymentMethodId(1)->errorCode(),
            InvalidCashPaymentMethodRoutingException::inconsistentTracking('aucun', 'individual')->errorCode(),
        ];
    }
}
