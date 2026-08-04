import { redirect } from 'next/navigation';

export default function SamplePagesIndexPage() {
    redirect('/admin/kitchen-sink/sample-pages/login');
}
