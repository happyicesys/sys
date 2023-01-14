import{_ as M}from"./Authenticated.2894f92e.js";import{_ as h}from"./Button.1d76c393.js";import O from"./Form.fef3260c.js";import{_ as P,r as T,T as p,a as V,b as A,c}from"./TableHeadSort.8ecdda5e.js";import{_ as D}from"./MultiSelect.b58d4ceb.js";import{i as x,j as H,o as m,g as _,a as t,b as f,w as s,F as I,H as R,d as e,t as i,m as J,p as C,c as S,f as r,J as k}from"./app.ad0996d0.js";import{r as q}from"./PlusIcon.e516c08f.js";import{r as z}from"./BackspaceIcon.321b30ba.js";import{r as G}from"./PencilSquareIcon.b8a08839.js";import{r as Q}from"./TrashIcon.f9065a8e.js";import"./open-closed.4c312123.js";import"./use-resolve-button-type.fe156099.js";import"./RectangleStackIcon.c7c7181f.js";import"./FormInput.89a1716a.js";import"./Modal.333b53d2.js";import"./ArrowUturnLeftIcon.92b36951.js";import"./CheckCircleIcon.9ddca09e.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=r(" Name "),oe=r(" Email "),ne={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},le={class:"flex space-x-1"},re=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),me={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},he=e("span",null,"of",-1),pe={class:"font-medium"},xe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},we={class:"bg-gray-100"},Ce={class:"divide-x divide-gray-200"},ke=r(" # "),$e=r(" Name "),Be=r(" Email "),Le=r(" Username "),Pe=r(" Role "),Ve=r(" Belongs to Operator "),Ie={class:"bg-white"},Se={class:"flex justify-center space-x-1"},Ne=e("span",null," Edit ",-1),je=e("span",null," Delete ",-1),Ke={key:0},Ue=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[Ue],tt={__name:"Index",props:{users:Object,countries:Object,operators:Object,roles:Object},setup(n){const l=x({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=x(!1),v=x(),y=x(""),b=x([]);H(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=b.value[0]});function N(){y.value="create",v.value=null,g.value=!0}function j(u){!confirm("Are you sure to delete "+u.name+"?")||k.Inertia.delete("/users/"+u.id)}function K(u){y.value="update",v.value=u,g.value=!0}function w(){k.Inertia.get("/users",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function U(){k.Inertia.get("/users")}function $(u){l.value.sortKey=u,l.value.sortBy=!l.value.sortBy,w()}function E(){g.value=!1}return(u,a)=>(m(),_(I,null,[t(f(R),{title:"Users"}),t(M,null,{header:s(()=>[W]),default:s(()=>{var B,L;return[e("div",X,[e("div",Y,[e("div",Z,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>N())},{default:s(()=>[t(f(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(P,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>l.value.name=o)},{default:s(()=>[se]),_:1},8,["modelValue"]),t(P,{placeholderStr:"Email",modelValue:l.value.email,"onUpdate:modelValue":a[2]||(a[2]=o=>l.value.email=o)},{default:s(()=>[oe]),_:1},8,["modelValue"])]),e("div",ne,[e("div",ae,[e("div",le,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=o=>w())},{default:s(()=>[t(f(T),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[4]||(a[4]=o=>U())},{default:s(()=>[t(f(z),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[ce,e("span",me,i((B=n.users.meta.from)!=null?B:0),1),fe,e("span",ge,i((L=n.users.meta.to)!=null?L:0),1),he,e("span",pe,i(n.users.meta.total),1),xe]),t(D,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":a[5]||(a[5]=o=>l.value.numberPerPage=o),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",ve,[e("div",ye,[e("table",be,[e("thead",we,[e("tr",Ce,[t(p,null,{default:s(()=>[ke]),_:1}),t(V,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:a[6]||(a[6]=o=>$("name"))},{default:s(()=>[$e]),_:1},8,["sortKey","sortBy"]),t(p,null,{default:s(()=>[Be]),_:1}),t(p,null,{default:s(()=>[Le]),_:1}),t(p,null,{default:s(()=>[Pe]),_:1}),t(V,{modelName:"operator_id",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:a[7]||(a[7]=o=>$("operator_id"))},{default:s(()=>[Ve]),_:1},8,["sortKey","sortBy"]),t(p)])]),e("tbody",Ie,[(m(!0),_(I,null,J(n.users.data,(o,d)=>(m(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-center"},{default:s(()=>[r(i(n.users.meta.from+d),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-left"},{default:s(()=>[r(i(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-left"},{default:s(()=>[r(i(o.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-center"},{default:s(()=>[r(i(o.username),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-center"},{default:s(()=>[r(i(o.roles[0]?o.roles[0].name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-center"},{default:s(()=>[r(i(o.operator.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(c,{currentIndex:d,totalLength:n.users.length,inputClass:"text-center"},{default:s(()=>[e("div",Se,[t(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>K(o)},{default:s(()=>[t(f(G),{class:"w-4 h-4"}),Ne]),_:2},1032,["onClick"]),t(h,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>j(o)},{default:s(()=>[t(f(Q),{class:"w-4 h-4"}),je]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.users.data.length?C("",!0):(m(),_("tr",Ke,Ee))])]),n.users.data.length?(m(),S(A,{key:0,links:n.users.links,meta:n.users.meta},null,8,["links","meta"])):C("",!0)])])])]),g.value?(m(),S(O,{key:0,user:v.value,operators:n.operators,roles:n.roles,type:y.value,showModal:g.value,onModalClose:E},null,8,["user","operators","roles","type","showModal"])):C("",!0)]}),_:1})],64))}};export{tt as default};
