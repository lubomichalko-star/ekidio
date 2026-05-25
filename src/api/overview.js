import { http } from './http';

export const overviewApi = {
  get: () => http.get('/overview'),
};

export default overviewApi;

