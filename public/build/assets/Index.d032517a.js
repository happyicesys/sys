import{_ as U}from"./Authenticated.6e746a9b.js";import{_}from"./Button.e8e1b8cd.js";import A from"./Form.377367d5.js";import{r as E,a as H,T as k,_ as R,b as J,c as h}from"./TableHeadSort.f0b0e858.js";import{_ as q}from"./SearchInput.5cebd3f1.js";import{_ as L}from"./MultiSelect.4a64ae74.js";import{i as u,j as z,o as i,g as m,a as t,b as g,w as n,F as C,H as G,d as e,t as d,m as N,p as $,c as I,f,J as P}from"./app.2287fc35.js";import{r as Q,a as W}from"./PlusIcon.051e391d.js";import{r as X}from"./TrashIcon.bbcadf46.js";import"./open-closed.8cb1004c.js";import"./use-resolve-button-type.7ea9563e.js";import"./RectangleStackIcon.038b8d49.js";import"./Modal.a9c41527.js";import"./ArrowUturnLeftIcon.a68b2225.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Tags) ",-1),Z={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),ae={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe=f(" Name "),ne={class:"col-span-5 md:col-span-1"},le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customers ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},de={class:"flex space-x-1"},ce=e("span",null," Search ",-1),ue=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),pe={class:"font-medium"},xe=e("span",null,"to",-1),_e={class:"font-medium"},he=e("span",null,"of",-1),ve={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ce={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Ve=f(" # "),Be=f(" Name "),Se=f(" Customers "),Le={class:"bg-white"},Ne={class:"inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border bg-gray-200"},Ie={class:"flex justify-center space-x-1"},Te=e("span",null," Edit ",-1),je=e("span",null," Delete ",-1),Me={key:0},Fe=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ke=[Fe],Ze={__name:"Index",props:{customers:Object,tags:Object},setup(l){const T=l,s=u({name:"",customers:[],sortKey:"",sortBy:!0,numberPerPage:100}),p=u(!1),v=u(),y=u(""),b=u([]),V=u([]);z(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=b.value[0],V.value=T.customers.data.map(r=>({id:r.id,code:r.code,name:r.name,is_active:r.is_active}))});function j(){y.value="create",v.value=null,p.value=!0}function M(r){!confirm("Are you sure to delete "+r.name+"?")||P.Inertia.delete("/tags/"+r.id)}function F(r){y.value="update",v.value=r,p.value=!0}function w(){P.Inertia.get("/tags",{...s.value,customers:s.value.customers.map(r=>r.id),numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function K(){P.Inertia.get("/tags")}function D(r){s.value.sortKey=r,s.value.sortBy=!s.value.sortBy,w()}function O(){p.value=!1}return(r,a)=>(i(),m(C,null,[t(g(G),{title:"Tags"}),t(U,null,{header:n(()=>[Y]),default:n(()=>{var B,S;return[e("div",Z,[e("div",ee,[e("div",te,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>j())},{default:n(()=>[t(g(Q),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",ae,[t(q,{placeholderStr:"Name",modelValue:s.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>s.value.name=o)},{default:n(()=>[oe]),_:1},8,["modelValue"]),e("div",ne,[le,t(L,{modelValue:s.value.customers,"onUpdate:modelValue":a[2]||(a[2]=o=>s.value.customers=o),options:V.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",re,[e("div",ie,[e("div",de,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=o=>w())},{default:n(()=>[t(g(E),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[4]||(a[4]=o=>K())},{default:n(()=>[t(g(H),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1})])]),e("div",me,[e("p",ge,[fe,e("span",pe,d((B=l.tags.meta.from)!=null?B:0),1),xe,e("span",_e,d((S=l.tags.meta.to)!=null?S:0),1),he,e("span",ve,d(l.tags.meta.total),1),ye]),t(L,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":a[5]||(a[5]=o=>s.value.numberPerPage=o),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",be,[e("div",we,[e("div",ke,[e("table",Ce,[e("thead",$e,[e("tr",Pe,[t(k,null,{default:n(()=>[Ve]),_:1}),t(R,{modelName:"name",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[6]||(a[6]=o=>D("name"))},{default:n(()=>[Be]),_:1},8,["sortKey","sortBy"]),t(k,null,{default:n(()=>[Se]),_:1}),t(k)])]),e("tbody",Le,[(i(!0),m(C,null,N(l.tags.data,(o,x)=>(i(),m("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[f(d(l.tags.meta.from+x),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[f(d(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[(i(!0),m(C,null,N(o.tagBindings.filter(function(c){return c.is_active}),c=>(i(),m("span",Ne,d(c.code)+", "+d(c.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[e("div",Ie,[t(_,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:c=>F(o)},{default:n(()=>[t(g(W),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"]),t(_,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:c=>M(o)},{default:n(()=>[t(g(X),{class:"w-4 h-4"}),je]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.tags.data.length?$("",!0):(i(),m("tr",Me,Ke))])]),l.tags.data.length?(i(),I(J,{key:0,links:l.tags.links,meta:l.tags.meta},null,8,["links","meta"])):$("",!0)])])])]),p.value?(i(),I(A,{key:0,tag:v.value,type:y.value,showModal:p.value,onModalClose:O},null,8,["tag","type","showModal"])):$("",!0)]}),_:1})],64))}};export{Ze as default};
