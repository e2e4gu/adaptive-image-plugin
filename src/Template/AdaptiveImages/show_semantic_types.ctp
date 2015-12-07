<table>
    <?= $this->Html->tableHeaders(['Semantic type', 'Resolution breakpoints', 'Options']); ?>
    
    <?php 
        foreach ($semanticTypes as $semanticType => $options) {
            echo $this->Html->tableCells([
                [$semanticType, '', ''],
            ]);
            
            foreach ($options as $resolution => $res_options) {
                echo $this->Html->tableCells([
                    ['', $resolution, json_encode($res_options)],
                ]);
            }
        }
     ?>
</table>

<style>
    th {
        border: solid 1px grey;
    }
    td {
        border: solid 1px rgb(96, 230, 206);
    }
    td.no-border {
        border: none;
    }
</style>