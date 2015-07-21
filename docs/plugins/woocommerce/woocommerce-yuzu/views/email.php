<table width="100%" border="0">
    <tr>
        <td colspan="8" align="center" style="padding: 10px;">
            <a href="<?php echo $urlLink.DS."0";?>">
                <img src="<?php echo $imgLink."/0.png"; ?>" alt="offer 0"/>
            </a>
            <table cellspacing="0" cellpadding="0" style="width: 100%; margin-top: 10px;">
                <tr>
                    <?php for($i=1; $i <= $nboffers; $i++ ) : ?>
                    <td align="center">
                        <a href="<?php echo $urlLink."/".$i;?>">
                            <img src="<?php echo $imgLink."/".$i.".png"; ?>" alt="offer <?php echo $i; ?>"/>
                        </a>
                    </td>
                    <?php if($i%2 == 0 && $i < $nboffers): ?></tr><tr><?php endif; ?>
                    <?php endfor; ?>
                </tr>
            </table>
        </td>
    </tr>
</table>