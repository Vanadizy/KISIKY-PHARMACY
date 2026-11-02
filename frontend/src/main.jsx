import React from 'react';
import {createRoot} from 'react-dom/client';
import {BrowserRouter} from 'react-router-dom';
import App from './App.jsx';
import './styles.css';

class AppErrorBoundary extends React.Component {
  constructor(props) { super(props); this.state = {error: null}; }
  static getDerivedStateFromError(error) { return {error}; }
  componentDidCatch(error, info) { console.error('Pharmacy app render error:', error, info.componentStack); }
  render() {
    if (this.state.error) return <main className="app-error-screen" role="alert"><div className="app-error-card"><span className="eyebrow">PHARMACY SYSTEM</span><h1>This page could not be displayed</h1><p>The application hit an unexpected display error. Reload the page to try again.</p><details><summary>Technical details</summary><pre>{String(this.state.error?.message || this.state.error)}</pre></details><button className="button button-primary" onClick={() => window.location.reload()}>Reload application</button></div></main>;
    return this.props.children;
  }
}

createRoot(document.getElementById('root')).render(<React.StrictMode><AppErrorBoundary><BrowserRouter future={{v7_startTransition: true, v7_relativeSplatPath: true}}><App/></BrowserRouter></AppErrorBoundary></React.StrictMode>);
