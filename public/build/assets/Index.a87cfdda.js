import{_ as M}from"./Authenticated.0b1c71d0.js";import{_ as g}from"./Button.9bf152f3.js";import K from"./Form.c4fd0294.js";import{_ as V,r as T,a as A,T as x,b as D,c as H,d as p}from"./TableHeadSort.1c1fd218.js";import{_ as O}from"./MultiSelect.62e437bc.js";import{i as _,j as R,o as c,g as h,a as t,b as m,w as s,F as B,H as J,d as e,t as d,m as q,p as k,c as L,f as r,J as C}from"./app.63b2c55f.js";import{r as z,a as G}from"./PlusIcon.3ca7d52d.js";import{r as Q}from"./TrashIcon.357219ed.js";import"./open-closed.b32d4935.js";import"./use-resolve-button-type.948c374b.js";import"./RectangleStackIcon.3c65f34a.js";import"./FormInput.1ffc6c48.js";import"./Modal.8e870ef8.js";import"./ArrowUturnLeftIcon.e92e1944.js";import"./CheckCircleIcon.0f0723d6.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.76c88547.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=r(" Name "),ae=r(" Email "),ne={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},le={class:"mt-3"},oe={class:"flex space-x-1"},re=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),me={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},pe=e("span",null,"of",-1),_e={class:"font-medium"},xe=e("span",null,"results",-1),he={class:"mt-6 flex flex-col"},ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},we={class:"bg-gray-100"},ke={class:"divide-x divide-gray-200"},Ce=r(" # "),$e=r(" Name "),Pe=r(" Email "),Ve=r(" Username "),Be={class:"bg-white"},Le={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Ie=e("span",null," Delete ",-1),Ne={key:0},Ue=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),je=[Ue],Ye={__name:"Index",props:{users:Object,countries:Object},setup(l){const o=_({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),f=_(!1),v=_(),y=_(""),b=_([]);R(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.numberPerPage=b.value[0]});function S(){y.value="create",v.value=null,f.value=!0}function I(i){!confirm("Are you sure to delete "+i.name+"?")||C.Inertia.delete("/users/"+i.id)}function N(i){y.value="update",v.value=i,f.value=!0}function w(){C.Inertia.get("/users",{...o.value,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function U(){C.Inertia.get("/users")}function j(i){o.value.sortKey=i,o.value.sortBy=!o.value.sortBy,w()}function E(){f.value=!1}return(i,n)=>(c(),h(B,null,[t(m(J),{title:"Users"}),t(M,null,{header:s(()=>[W]),default:s(()=>{var $,P;return[e("div",X,[e("div",Y,[e("div",Z,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=a=>S())},{default:s(()=>[t(m(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(V,{placeholderStr:"Name",modelValue:o.value.name,"onUpdate:modelValue":n[1]||(n[1]=a=>o.value.name=a)},{default:s(()=>[se]),_:1},8,["modelValue"]),t(V,{placeholderStr:"Email",modelValue:o.value.email,"onUpdate:modelValue":n[2]||(n[2]=a=>o.value.email=a)},{default:s(()=>[ae]),_:1},8,["modelValue"])]),e("div",ne,[e("div",le,[e("div",oe,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[3]||(n[3]=a=>w())},{default:s(()=>[t(m(T),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[4]||(n[4]=a=>U())},{default:s(()=>[t(m(A),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[ce,e("span",me,d(($=l.users.meta.from)!=null?$:0),1),fe,e("span",ge,d((P=l.users.meta.to)!=null?P:0),1),pe,e("span",_e,d(l.users.meta.total),1),xe]),t(O,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":n[5]||(n[5]=a=>o.value.numberPerPage=a),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",he,[e("div",ve,[e("div",ye,[e("table",be,[e("thead",we,[e("tr",ke,[t(x,null,{default:s(()=>[Ce]),_:1}),t(D,{modelName:"name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[6]||(n[6]=a=>j("name"))},{default:s(()=>[$e]),_:1},8,["sortKey","sortBy"]),t(x,null,{default:s(()=>[Pe]),_:1}),t(x,null,{default:s(()=>[Ve]),_:1}),t(x)])]),e("tbody",Be,[(c(!0),h(B,null,q(l.users.data,(a,u)=>(c(),h("tr",{key:a.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:u,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(d(l.users.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:u,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(d(a.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:u,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(d(a.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:u,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(d(a.username),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:u,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[e("div",Le,[t(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>N(a)},{default:s(()=>[t(m(G),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"]),t(g,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>I(a)},{default:s(()=>[t(m(Q),{class:"w-4 h-4"}),Ie]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.users.data.length?k("",!0):(c(),h("tr",Ne,je))])]),l.users.data.length?(c(),L(H,{key:0,links:l.users.links,meta:l.users.meta},null,8,["links","meta"])):k("",!0)])])])]),f.value?(c(),L(K,{key:0,user:v.value,type:y.value,showModal:f.value,onModalClose:E},null,8,["user","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Ye as default};
