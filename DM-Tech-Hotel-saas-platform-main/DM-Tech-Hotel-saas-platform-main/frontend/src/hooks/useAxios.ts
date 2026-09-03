import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';

export const useAxios = <T = any>(config: AxiosRequestConfig, manual = false) => {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState<boolean>(!manual);
  const [error, setError] = useState<AxiosError | null>(null);

  const execute = async (overrideConfig?: AxiosRequestConfig) => {
    setLoading(true);
    try {
      const response: AxiosResponse<T> = await api.request({
        ...config,
        ...overrideConfig,
      });
      setData(response.data);
      setError(null);
      return response.data;
    } catch (err) {
      setError(err as AxiosError);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!manual) {
      execute();
    }
  }, []);

  return { data, loading, error, execute };
};
