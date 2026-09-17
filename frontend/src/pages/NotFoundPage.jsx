import { Link } from 'react-router'
import { EmptyState } from '../components/Feedback'

export default function NotFoundPage() {
  return (
    <EmptyState icon="🧭" title="Page not found">
      <p>The page you are looking for doesn’t exist or has moved.</p>
      <Link to="/" className="btn btn-primary">
        Back to the menu
      </Link>
    </EmptyState>
  )
}
