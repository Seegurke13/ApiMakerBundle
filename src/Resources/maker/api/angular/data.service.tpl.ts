import {Injectable} from "@angular/core";
import {AbstractSymfonyDataService} from "./abstract-symfony-data.service";

@Injectable()
export class <?= $entity_class_name ?>DataService extends AbstractSymfonyDataService<<?= $entity_class_name ?>> {
    dataModel: string = '<?= $entityVarSingular ?>';
}