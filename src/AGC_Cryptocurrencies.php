<?php

// Crypto Helper
class AGC_Cryptocurrencies {

	public static function get() {
        // id, name, round_precision, icon_filename, refresh_time, symbol, has_electrum, has_payment_verification, needs_confirmations
		$cryptoArray = array(
            
            // electrum
            'BTC' => new AGC_Cryptocurrency('BTC', 'Bitcoin', 8, 'bitcoin_logo_small.png', 60, '₿', true, true, true),            
            'LTC' => new AGC_Cryptocurrency('LTC', 'Litecoin', 8, 'litecoin_logo_small.png', 60, 'Ł', true, true, true),
            'QTUM' => new AGC_Cryptocurrency('QTUM', 'Qtum', 8, 'qtum_logo_small.png', 60, '', true, false, true),

            // auto-pay coins
            'DOGE' => new AGC_Cryptocurrency('DOGE', 'Dogecoin', 8, 'dogecoin_logo_small.png', 60, 'Ð', false, true, true),
            'ETH' => new AGC_Cryptocurrency('ETH', 'Ethereum', 18, 'ethereum_logo_small.png', 60, 'Ξ', false, true, true),
            'ADA' => new AGC_Cryptocurrency('ADA', 'Cardano', 6, 'cardano_logo_small.png', 60, '', false, true, false),
            'XTZ' => new AGC_Cryptocurrency('XTZ', 'Tezos', 6, 'tezos_logo_small.png', 60, '', false, true, false),
            'TRX' => new AGC_Cryptocurrency('TRX', 'Tron', 6, 'tron_logo_small.png', 60, '', false, true, false),
            'XLM' => new AGC_Cryptocurrency('XLM', 'Stellar', 7, 'stellar_logo_small.png', 60, '', false, true, false),
            'EOS' => new AGC_Cryptocurrency('EOS', 'EOS', 4, 'eos_logo_small.png', 60, '', false, true, false),
            'BSV' => new AGC_Cryptocurrency('BSV', 'Bitcoin SV', 8, 'bitcoinsv_logo_small.png', 60, '', false, true, false),
            'ZEC' => new AGC_Cryptocurrency('ZEC', 'Zcash', 8, 'zcash_logo_small.png', 60, 'ⓩ', false, true, true),
            'DASH' => new AGC_Cryptocurrency('DASH', 'Dash', 8, 'dash_logo_small.png', 60, '', false, true, true),
            'XRP' => new AGC_Cryptocurrency('XRP', 'Ripple', 6, 'ripple_logo_small.png', 60, '', false, true, false),
            'BCH' => new AGC_Cryptocurrency('BCH', 'Bitcoin Cash', 8, 'bitcoincash_logo_small.png', 60, '', false, true, true),
            'ONION' => new AGC_Cryptocurrency('ONION', 'DeepOnion', 8, 'deeponion_logo_small.png', 60, '', false, true, true),
            'BLK' => new AGC_Cryptocurrency('BLK', 'BlackCoin', 8, 'blackcoin_logo_small.png', 60, '', false, true, true),
            'ETC' => new AGC_Cryptocurrency('ETC', 'Ethereum Classic', 18, 'ethereumclassic_logo_small.png', 60, '', false, true, true),
            'LSK' => new AGC_Cryptocurrency('LSK', 'Lisk', 8, 'lisk_logo_small.png', 60, '', false, true, true),
            'XEM' => new AGC_Cryptocurrency('XEM', 'NEM', 6, 'nem_logo_small.png', 60, '', false, true, true),
            'WAVES' => new AGC_Cryptocurrency('WAVES', 'Waves', 8, 'waves_logo_small.png', 60, '', false, true, true),
            'DCR' => new AGC_Cryptocurrency('DCR', 'Decred', 8, 'decred_logo_small.png', 60, '', false, true, true),

            // tokens
            'HOT' => new AGC_Cryptocurrency('HOT', 'Holochain', 18, 'holochain_logo_small.png', 60, '', false, true, true),
            'LINK' => new AGC_Cryptocurrency('LINK', 'Chainlink', 18, 'chainlink_logo_small.png', 60, '', false, true, true),
            'BAT' => new AGC_Cryptocurrency('BAT', 'Basic Attention Token', 18, 'basicattentiontoken_logo_small.png', 60, '', false, true, true),
            'BNB' => new AGC_Cryptocurrency('BNB', 'Binance Coin', 18, 'binancecoin_logo_small.png', 60, '', false, true, true),
            'MKR' => new AGC_Cryptocurrency('MKR', 'Maker', 18, 'maker_logo_small.png', 60, '', false, true, true),
            'OMG' => new AGC_Cryptocurrency('OMG', 'OmiseGO', 18, 'omisego_logo_small.png', 60, '', false, true, true),
            'REP' => new AGC_Cryptocurrency('REP', 'Augur', 18, 'augur_logo_small.png', 60, '', false, true, true),
            'GNO' => new AGC_Cryptocurrency('GNO', 'Gnosis', 18, 'gnosis_logo_small.png', 60, '', false, true, true),
            'MLN' => new AGC_Cryptocurrency('MLN', 'Melon', 18, 'melon_logo_small.png', 60, '', false, true, true),
            'ZRX' => new AGC_Cryptocurrency('ZRX', '0x', 18, 'zrx_logo_small.png', 60, '', false, true, true),
            'GUSD' => new AGC_Cryptocurrency('GUSD', 'Gemini Dollar', 2, 'geminidollar_logo_small.png', 60, '', false, true, true),

            // no support
            'XMR' => new AGC_Cryptocurrency('XMR', 'Monero', 12, 'monero_logo_small.png', 60, 'ɱ', false, false, true),
            'VRC' => new AGC_Cryptocurrency('VRC', 'Vericoin', 8, 'vericoin_logo_small.png', 60, '', false, false, true),
            'BTG' => new AGC_Cryptocurrency('BTG', 'Bitcoin Gold', 8, 'bitcoingold_logo_small.png', 60, '', false, false, true),
            'VET' => new AGC_Cryptocurrency('VET', 'VeChain', 18, 'vechain_logo_small.png', 60, '', false, false, true),
            'BCD' => new AGC_Cryptocurrency('BCD', 'Bitcoin Diamond', 8, 'bitcoindiamond_logo_small.png', 60, '', false, false, true),
            'BCN' => new AGC_Cryptocurrency('BCN', 'Bytecoin', 8, 'bytecoin_logo_small.png', 60, '', false, false, true),
            'DGB' => new AGC_Cryptocurrency('DGB', 'Digibyte', 8, 'digibyte_logo_small.png', 60, '', false, false, true),
            
            // More searching required
            
            'POT' => new AGC_Cryptocurrency('POT', 'Potcoin', 18, 'potcoin_logo_small.png', 60, '', false, false, true),            
            // https://www.reddit.com/r/OntologyNetwork/comments/9duf28/api_to_get_ont_balance/
            'ONT' => new AGC_Cryptocurrency('ONT', 'Ontology', 18, 'ontology_logo_small.png', 60, '', false, false, true),
            
            // TODO
            
            // https://api.iogateway.cloud/api/Tangle/address/SDCUDAWKRZWFJFWROUAYVTKLZIGDNBDMBLZIWFWNXZLFRKPUGECMMZGPUFYZGANUZEP9VRPTFTVCKZVAWVRJTWZQDD/transactions
            'MIOTA' => new AGC_Cryptocurrency('MIOTA', 'Iota', 18, 'iota_logo_small.png', 60, '', false, false, true),
        );

        return $cryptoArray;
	}

    // Php likes to convert numbers to scientific notation, so this handles displaying small amounts correctly
    public static function get_price_string($cryptoId, $amount) {
        $cryptos = self::get();
        $crypto = $cryptos[$cryptoId];

        // Round based on smallest unit of crypto
        $roundedAmount = round($amount, $crypto->get_round_precision(), PHP_ROUND_HALF_UP);

        // Forces displaying the number in decimal format, with as many zeroes as possible to display the smallest unit of crypto
        $formattedAmount = number_format($roundedAmount, $crypto->get_round_precision(), '.', '');

        // We probably have extra 0's on the right side of the string so trim those
        $amountWithoutZeroes = rtrim($formattedAmount, '0');

        // If it came out to an round whole number we have a dot on the right side, so take that off
        $amountWithoutTrailingDecimal = rtrim($amountWithoutZeroes, '.');

        return $amountWithoutTrailingDecimal;
    }

	public static function is_valid_wallet_address($cryptoId, $address) {
            
        if ($cryptoId === 'BTC') {
            return preg_match('/[13][a-km-zA-HJ-NP-Z0-9]{26,42}|bc[a-z0-9]{8,87}/', $address);
        }
        if ($cryptoId === 'ETH') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'XMR') {
            // return preg_match('/4[0-9AB][1-9A-HJ-NP-Za-km-z]{93}/', $address);

            // 2-15-2019 not testing Monero
            return strlen($address) > 5;
        }
        if ($cryptoId === 'DOGE'){
            return preg_match('/D{1}[5-9A-HJ-NP-U]{1}[1-9A-HJ-NP-Za-km-z]{32}/', $address);
        }
        if ($cryptoId === 'LTC') {
            return preg_match('/[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}|l[a-z0-9]{8,87}/', $address);
        }
        if ($cryptoId === 'ZEC') {
            $isTAddr =  preg_match('/t1[a-zA-Z0-9]{33,36}/', $address);
            $isZAddr = preg_match('/z[a-zA-Z0-9]{90,96}/', $address);

            return $isTAddr || $isZAddr;
        }
        if ($cryptoId === 'BCH') {
            $isOldAddress = preg_match('/[13][a-km-zA-HJ-NP-Z1-9]{25,42}/', $address);
            $isNewAddress1 = preg_match('/(q|p)[a-z0-9]{41}/', $address);
            $isNewAddress2 = preg_match('/(Q|P)[A-Z0-9]{41}/', $address);

            return $isOldAddress || $isNewAddress1 || $isNewAddress2;
        }
        if ($cryptoId === 'DASH') {
            return preg_match('/X[1-9A-HJ-NP-Za-km-z]{33}/', $address);
        }
        if ($cryptoId === 'XRP') {
            return preg_match('/r[0-9a-zA-Z]{33}/', $address);
        }
        if ($cryptoId === 'ONION') {
            return preg_match('/D[0-9a-zA-Z]{33}/', $address);
        }
        if ($cryptoId === 'BLK') {
            return preg_match('/B[0-9a-zA-Z]{32,36}/', $address);
        }
        if ($cryptoId === 'VRC') {
            return preg_match('/V[0-9a-zA-Z]{32,36}/', $address);
        }
        if ($cryptoId === 'ETC') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'REP') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'BTG') {
            return preg_match('/[AG][a-km-zA-HJ-NP-Z0-9]{26,42}|bt[a-z0-9]{8,87}/', $address);
        }
        if ($cryptoId === 'EOS') {
            return strlen($address) == 12;
        }
        if ($cryptoId === 'BSV') {
            return preg_match('/[13][a-km-zA-HJ-NP-Z0-9]{26,42}|q[a-z0-9]{9,88}/', $address);
        }
        if ($cryptoId === 'VET') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'TRX') {
            return preg_match('/T[a-km-zA-HJ-NP-Z0-9]{26,42}/', $address);
        }
        if ($cryptoId === 'XLM') {
            return preg_match('/G[A-Z0-9]{55}/', $address);
        }
        if ($cryptoId === 'QTUM') {
            return preg_match('/Q[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'ADA') {
            return preg_match('/Ddz[0-9a-zA-Z]{80,120}/', $address);
        }
        if ($cryptoId === 'XTZ') {
            return preg_match('/tz1[0-9a-zA-Z]{30,39}/', $address);
        }
        if ($cryptoId === 'MLN') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'GNO') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'ONT') {
            return preg_match('/A[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'BAT') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'BCD') {
            return preg_match('/1[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'BCN') {
            return preg_match('/2[0-9a-zA-Z]{91,99}/', $address);
        }
        if ($cryptoId === 'BNB') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'DCR') {
            return preg_match('/D[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'DGB') {
            return preg_match('/D[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'HOT') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'LINK') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'LSK') {
            return preg_match('/[0-9a-zA-Z]{17,22}L/', $address);
        }
        if ($cryptoId === 'MIOTA') {
            return preg_match('/[0-9a-zA-Z]{85,95}/', $address);
        }
        if ($cryptoId === 'MKR') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'OMG') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'POT') {
            return preg_match('/P[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'WAVES') {
            return preg_match('/3[0-9a-zA-Z]{31,35}/', $address);
        }
        if ($cryptoId === 'XEM') {
            return preg_match('/N[0-9a-zA-Z]{35,45}/', $address);
        }
        if ($cryptoId === 'ZRX') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        if ($cryptoId === 'GUSD') {
            return preg_match('/0x[a-fA-F0-9]{40,42}/', $address);
        }
        
        AGC_Util::log(__FILE__, __LINE__, 'Invalid cryptoId, contact plug-in developer.');        
        throw new Exception('Invalid cryptoId, contact plug-in developer.');
    }
}

?>