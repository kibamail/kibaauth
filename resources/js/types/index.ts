export interface Client {
  id: string;
  name: string;
  secret: string;
  redirect_uris: string[];
  revoked: boolean;
  created_at: string;
  updated_at: string;
}
