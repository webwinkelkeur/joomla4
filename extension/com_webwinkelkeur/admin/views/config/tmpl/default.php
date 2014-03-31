<?php

defined('_JEXEC') or die('Restricted Access');

JHtml::_('behavior.tooltip');

$config = $this->config;

?>
<form action="<?php echo JRoute::_('index.php?option=com_webwinkelkeur'); ?>" method="POST" name="adminForm" id="adminForm">
    <table class="wwk-form">
        <tr valign="top">
            <th scope="row"><label for="wwk-shop-id">Webwinkel ID</label></th>
            <td><input name="webwinkelkeur_wwk_shop_id" type="text" id="wwk-shop-id" value="<?php echo htmlspecialchars($config['wwk_shop_id'], ENT_QUOTES, 'UTF-8'); ?>" class="regular-text" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="wwk-api-key">API key</label></th>
            <td><input name="webwinkelkeur_wwk_api_key" type="text" id="wwk-api-key" value="<?php echo htmlspecialchars($config['wwk_api_key'], ENT_QUOTES, 'UTF-8'); ?>" class="regular-text" />
            <p class="description">
            Deze gegevens vindt u na het inloggen op <a href="https://www.webwinkelkeur.nl/webwinkel/" target="_blank">WebwinkelKeur.nl</a>.<br />Klik op 'Keurmerk plaatsen'. De gegevens zijn vervolgens onderaan deze pagina te vinden.
            </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-sidebar">Sidebar weergeven</label></th>
            <td>
                <label>
                    <input type="checkbox" id="webwinkelkeur-sidebar" name="webwinkelkeur_sidebar" value="1" <?php if($config['sidebar']) echo 'checked'; ?> />
                    Ja, voeg de WebwinkelKeur Sidebar toe aan mijn website.
                </label>
            </td>
        </tr> 
        <tr valign="top">
            <th scope="row">Sidebar positie</th>
            <td>
                <fieldset>
                    <label>
                        <input type="radio" name="webwinkelkeur_sidebar_position" value="left" <?php if($config['sidebar_position'] == 'left') echo 'checked'; ?> />
                        Links
                    </label><br>
                    <label>
                        <input type="radio" name="webwinkelkeur_sidebar_position" value="right" <?php if($config['sidebar_position'] == 'right') echo 'checked'; ?> />
                        Rechts
                    </label>
                </fieldset>
            </td>
        </tr> 
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-sidebar-top">Sidebar hoogte</label></th>
            <td><input name="webwinkelkeur_sidebar_top" type="text" id="webwinkelkeur-sidebar-top" value="<?php echo htmlspecialchars($config['sidebar_top'], ENT_QUOTES, 'UTF-8'); ?>" class="small-text" />
            <p class="description">
            Aantal pixels vanaf de bovenkant.
            </p>
            </td>
        </tr>
        <?php if($this->virtuemart): ?>
        <tr valign="top">
            <th scope="row">Uitnodigingen versturen</th>
            <td>
                <fieldset>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="1" <?php if($config['invite'] == 1) echo 'checked'; ?> />
                        Ja, na elke bestelling.
                    </label><br>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="2" <?php if($config['invite'] == 2) echo 'checked'; ?> />
                        Ja, alleen bij de eerste bestelling.
                    </label><br>
                    <label>
                        <input type="radio" name="webwinkelkeur_invite" value="0" <?php if(!$config['invite']) echo 'checked'; ?> />
                        Nee, geen uitnodigingen versturen.
                    </label>
                </fieldset>
                <p class="description">Deze functionaliteit is alleen beschikbaar voor Plus-leden.</p>
            </td>
        </tr> 
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-invite-delay">Wachttijd voor uitnodiging</label></th>
            <td><input name="webwinkelkeur_invite_delay" type="text" id="webwinkelkeur-invite-delay" value="<?php echo htmlspecialchars($config['invite_delay'], ENT_QUOTES, 'UTF-8'); ?>" class="small-text" />
            <p class="description">
            De uitnodiging wordt verstuurd nadat het opgegeven aantal dagen is verstreken na het verzenden van de bestelling.
            </p>
            </td>
        </tr>
        <?php endif; ?>
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-tooltip">Tooltip weergeven</label></th>
            <td>
                <label>
                    <input type="checkbox" id="webwinkelkeur-tooltip" name="webwinkelkeur_tooltip" value="1" <?php if($config['tooltip']) echo 'checked'; ?> />
                    Ja, voeg de WebwinkelKeur Tooltip toe aan mijn website.
                </label>
            </td>
        </tr> 
        <tr valign="top">
            <th scope="row"><label for="webwinkelkeur-javascript">JavaScript-integratie</label></th>
            <td>
                <label>
                    <input type="checkbox" id="webwinkelkeur-javascript" name="webwinkelkeur_javascript" value="1" <?php if($config['javascript']) echo 'checked'; ?> />
                    Ja, voeg de WebwinkelKeur JavaScript toe aan mijn website.
                </label>
            </td>
        </tr> 
    </table>
    <div>
		<input type="hidden" name="task" value="" />
		<?php echo JHtml::_('form.token'); ?>
    </div>
</form>
