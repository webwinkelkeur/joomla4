<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted Access');

JHtml::_('behavior.tooltip');

$config = $this->config;

?>
<form action="<?php echo JRoute::_('index.php?option=com_webwinkelkeur'); ?>" method="POST" name="adminForm" id="adminForm">
    <table class="wwk-form">
        <tr valign="top">
            <th scope="row"><label for="wwk-shop-id">Webwinkel ID</label></th>
            <td><input name="webwinkelkeur_wwk_shop_id" type="text" id="wwk-shop-id" value="<?php echo htmlspecialchars(@$config['wwk_shop_id'], ENT_QUOTES, 'UTF-8'); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wwk-api-key">API key</label></th>
            <td><input name="webwinkelkeur_wwk_api_key" type="text" id="wwk-api-key" value="<?php echo htmlspecialchars(@$config['wwk_api_key'], ENT_QUOTES, 'UTF-8'); ?>" class="regular-text" />
            <p class="description">
            Deze gegevens vindt u na het inloggen op <a href="https://dashboard.webwinkelkeur.nl" target="_blank">WebwinkelKeur Dashboard</a>.<br />Klik op 'Installatie' > 'Wizard'. De gegevens zijn vervolgens in de uitleg op deze pagina te vinden.
            </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-javascript">JavaScript-integratie</label></th>
            <td>
                <label>
                    <input type="checkbox" id="webwinkelkeur-javascript" name="webwinkelkeur_javascript" value="1" <?php if(@$config['javascript']) echo 'checked'; ?> />
                    Ja, voeg de WebwinkelKeur JavaScript toe aan mijn website.
                </label>
                <p class="description">
                Gebruik de JavaScript-integratie om de sidebar en de tooltip op uw site te plaatsen.<br />
                Alle instellingen voor de sidebar en de tooltip, vindt u in het <a href="https://dashboard.webwinkelkeur.nl/integration" target="_blank">WebwinkelKeur Dashboard</a>.
                </p>
            </td>
        </tr> 
        <?php if($this->virtuemart OR $this->hikashop): ?>
        <tr valign="top">
            <th scope="row">Uitleg Uitnodigingen versturen</th>
            <td>
            <p class="description">
            <?php if($this->virtuemart) echo 'VirtueMart verstuurt de uitnodiging x dagen na het moment dat de bestelling de status S (shipped) heeft bereikt.'; ?><br />
            <?php if($this->hikashop) echo 'HikaShop verstuurt de uitnodiging x dagen na het moment dat een bestelling de status Shipped krijgt.'; ?>
            </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Uitnodigingen versturen</th>
            <td>
                <fieldset>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="1" <?php if(@$config['invite'] == 1) echo 'checked'; ?> />
                        Ja, na elke bestelling.
                    </label><br>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="2" <?php if(@$config['invite'] == 2) echo 'checked'; ?> />
                        Ja, alleen bij de eerste bestelling.
                    </label><br>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="0" <?php if(!@$config['invite']) echo 'checked'; ?> />
                        Nee, geen uitnodigingen versturen.
                    </label>
                </fieldset>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"></th>
            <td>
                <fieldset>
                    <label>
                        <input type="checkbox" name="webwinkelkeur_limit_order_data" value="1" <?php if(@$config['limit_order_data']) echo 'checked';?> />
                        Stuur geen uitgebreide informatie over bestellingen naar WebwinkelKeur
                    </label>
                    <p class="description">
                        Standaard sturen we informatie over de klant en de bestelde producten mee bij het aanvragen van uitnodigingen, zodat we extra mogelijkheden kunnen bieden.<br />
                        Als u hier een vinkje zet, gebeurt dat niet, en is niet alle WebwinkelKeur-functionaliteit beschikbaar.
                    </p>
                </fieldset>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-invite-delay">Wachttijd voor uitnodiging</label></th>
            <td><input name="webwinkelkeur_invite_delay" type="text" id="webwinkelkeur-invite-delay" value="<?php echo htmlspecialchars(@$config['invite_delay'], ENT_QUOTES, 'UTF-8'); ?>" class="small-text" />
            <p class="description">
            De uitnodiging wordt verstuurd nadat het opgegeven aantal dagen is verstreken na het verzenden van de bestelling (status <strong>shipped</strong>!).
            </p>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <div>
		<input type="hidden" name="task" value="" />
		<?php echo JHtml::_('form.token'); ?>
    </div>
</form>
