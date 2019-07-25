import {Observable} from 'rxjs';
import {Injectable} from '@angular/core';
import {ApiUtils} from '@mediacologne/ng-utility';
import {AuthService} from "@mediacologne/ng-auth";
import {environment} from "../../../../../environments/environment";


@Injectable()
export abstract class AbstractSymfonyDataService<T> {
    abstract dataModel: string;

    /**
     * Request a dataObject
     *
     * @param id
     */
    public get(id: any | number): Observable<T> {
        if (typeof id === 'object') {
            id = id.id;
        }

        return this.getData(
            this.http.get(
                environment.backend.transport.baseUrl + this.dataModel + '/' + id,
                {
                    headers: ApiUtils.getHeader(AuthService.getAuthToken()),
                    observe: 'response'
                }
            )
        );
    }

    /**
     * Request all dataObjects
     *
     * @returns {Observable<Object>}
     */
    public getAll(): Observable<T[]> {
        return this.getData(
            this.http.get(environment.backend.transport.baseUrl + this.dataModel,
                {
                    headers: ApiUtils.getHeader(''),
                    observe: 'response'
                }
            )
        );
    }

    /**
     * Update a dataObject
     *
     * @param {Object} dataObject
     * @returns {Observable<Object>}
     */
    public edit(dataObject: any): Observable<T> {
        return this.getData(
            this.http.post(environment.backend.transport.baseUrl + this.dataModel + '/update/' + dataObject.id,
                dataObject,
                {
                    headers: ApiUtils.getHeader(AuthService.getAuthToken()),
                    observe: 'response'
                }
            )
        );
    }

    /**
     * Creates a dataObject
     *
     * @param {Object} dataObject
     * @returns {Observable<Object>}
     */
    public create(dataObject: any): Observable<T> {
        return this.getData(
            this.http.post(environment.backend.transport.baseUrl + this.dataModel + '/new',
                dataObject,
                {
                    headers: ApiUtils.getHeader(AuthService.getAuthToken()),
                    observe: 'response'
                }
            )
        );
    }

    /**
     * Remove a dataObject
     *
     * @param {Object} dataObject
     * @returns {Observable<Object>}
     */
    public remove(dataObject: any): Observable<void> {
        return this.getData(
            this.http.get(environment.backend.transport.baseUrl + this.dataModel + '/delete/' + dataObject.id,
                {
                    headers: ApiUtils.getHeader(AuthService.getAuthToken()),
                    observe: 'response'
                }
            )
        );
    }


    /**
     * Get the data from a request
     *
     * @param {Observable<any>} request
     * @returns {Observable<any>}
     */
    protected getData(request: Observable<any>): Observable<any> {
        return Observable.create((observer: any) => {
            request.subscribe(response => {
                if (typeof response === 'object') {
                    if (response.headers && response.headers.has('X-Packer') && response.headers.get('X-Packer') == 'ApiResponsePacker') {
                        observer.next(response.body.data);
                    } else if (response.body) {
                        observer.next(response.body);
                    } else {
                        observer.next(response);
                    }
                } else {
                    observer.next(response);
                }
            });
        });
    }
}
