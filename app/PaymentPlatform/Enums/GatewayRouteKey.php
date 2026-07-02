<?php

namespace App\PaymentPlatform\Enums;

enum GatewayRouteKey: string
{
    case WalletCollection = 'wallet_collection';
    case WalletDisbursement = 'wallet_disbursement';
    case BankCollection = 'bank_collection';
    case BankDisbursement = 'bank_disbursement';
    case CardCollection = 'card_collection';

    public function label(): string
    {
        return match ($this) {
            self::WalletCollection => 'Wallet Collections',
            self::WalletDisbursement => 'Wallet Disbursements',
            self::BankCollection => 'Bank Collections',
            self::BankDisbursement => 'Bank Disbursements',
            self::CardCollection => 'Card Collections',
        };
    }

    public function displayLabel(): string
    {
        return match ($this) {
            self::WalletCollection => 'Wallet & Mobile Money Collections',
            self::WalletDisbursement => 'Wallet & Mobile Money Disbursements',
            self::BankCollection => 'Bank Collections',
            self::BankDisbursement => 'Bank Account Disbursements',
            self::CardCollection => 'Card Collections',
        };
    }

    public function summaryTitle(): string
    {
        return $this->label();
    }

    public function helpText(): string
    {
        return match ($this) {
            self::WalletCollection => 'Routes customer mobile wallet and mobile money repayments through the selected gateway.',
            self::WalletDisbursement => 'Routes loan disbursements to customer mobile wallets through the selected gateway.',
            self::BankCollection => 'Routes bank transfer repayments through the selected gateway.',
            self::BankDisbursement => 'Routes loan disbursements to customer bank accounts through the selected gateway.',
            self::CardCollection => 'Routes card payment collections through the selected gateway when a card provider is available.',
        };
    }

    public function direction(): GatewayDirection
    {
        return match ($this) {
            self::WalletCollection, self::BankCollection, self::CardCollection => GatewayDirection::Collection,
            self::WalletDisbursement, self::BankDisbursement => GatewayDirection::Disbursement,
        };
    }

    public function paymentMethod(): GatewayPaymentMethod
    {
        return match ($this) {
            self::WalletCollection, self::WalletDisbursement => GatewayPaymentMethod::MobileMoney,
            self::BankCollection, self::BankDisbursement => GatewayPaymentMethod::Bank,
            self::CardCollection => GatewayPaymentMethod::Card,
        };
    }

    /**
     * @return array{collections: bool, disbursements: bool, mobile_money: bool, bank: bool}
     */
    public function requiredGatewayCapabilities(): array
    {
        return match ($this) {
            self::WalletCollection => [
                'collections' => true,
                'disbursements' => false,
                'mobile_money' => true,
                'bank' => false,
            ],
            self::WalletDisbursement => [
                'collections' => false,
                'disbursements' => true,
                'mobile_money' => true,
                'bank' => false,
            ],
            self::BankCollection => [
                'collections' => true,
                'disbursements' => false,
                'mobile_money' => false,
                'bank' => true,
            ],
            self::BankDisbursement => [
                'collections' => false,
                'disbursements' => true,
                'mobile_money' => false,
                'bank' => true,
            ],
            self::CardCollection => [
                'collections' => true,
                'disbursements' => false,
                'mobile_money' => false,
                'bank' => false,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function adminTableRoutes(): array
    {
        return [
            self::WalletCollection,
            self::BankCollection,
            self::WalletDisbursement,
            self::BankDisbursement,
        ];
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::WalletCollection,
            self::WalletDisbursement,
            self::BankDisbursement,
            self::BankCollection,
            self::CardCollection,
        ];
    }
}
