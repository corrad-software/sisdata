import { redirect } from 'next/navigation';
import { getServerUser } from '@/lib/auth-server';

export default async function RootPage() {
    const user = await getServerUser();
    redirect(user ? '/admin' : '/login');
}
