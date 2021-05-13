const Index = () => import('./components/l-limitless-bs4/Index');
const Form = () => import('./components/l-limitless-bs4/Form');
const Show = () => import('./components/l-limitless-bs4/Show');
const SideBarLeft = () => import('./components/l-limitless-bs4/SideBarLeft');
const SideBarRight = () => import('./components/l-limitless-bs4/SideBarRight');

const routes = [

    {
        path: '/estimates',
        components: {
            default: Index,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Estimates',
            metaTags: [
                {
                    name: 'description',
                    content: 'Estimates'
                },
                {
                    property: 'og:description',
                    content: 'Estimates'
                }
            ]
        }
    },
    {
        path: '/estimates/create',
        components: {
            default: Form,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Estimate :: Create',
            metaTags: [
                {
                    name: 'description',
                    content: 'Create Estimate'
                },
                {
                    property: 'og:description',
                    content: 'Create Estimate'
                }
            ]
        }
    },
    {
        path: '/estimates/:id',
        components: {
            default: Show,
            'sidebar-left': SideBarLeft,
            'sidebar-right': SideBarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Estimate',
            metaTags: [
                {
                    name: 'description',
                    content: 'Estimate'
                },
                {
                    property: 'og:description',
                    content: 'Estimate'
                }
            ]
        }
    },
    {
        path: '/estimates/:id/copy',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Sales :: Estimate :: Copy',
            metaTags: [
                {
                    name: 'description',
                    content: 'Copy Estimate'
                },
                {
                    property: 'og:description',
                    content: 'Copy Estimate'
                }
            ]
        }
    },
    {
        path: '/estimates/:id/edit',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Sales :: Estimate :: Edit',
            metaTags: [
                {
                    name: 'description',
                    content: 'Edit Estimate'
                },
                {
                    property: 'og:description',
                    content: 'Edit Estimate'
                }
            ]
        }
    }

];

export default routes;
