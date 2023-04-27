import{_ as A}from"./Authenticated.f8857e77.js";import{_ as p}from"./Button.2754726f.js";import{_ as E,r as R,T as B,a as H,b as g}from"./TableData.d8e4f8bc.js";import{_ as S}from"./MultiSelect.14a59782.js";import{_ as V}from"./TableHeadSort.4023abc7.js";import{h as d,j as Z,f as v,a as s,u as c,w as o,F as L,o as u,Z as q,b as e,d as i,t as m,l as z,m as w,c as I,O as k,S as G}from"./app.4afcab37.js";import{r as J}from"./PlusIcon.f8d8d741.js";import{r as Q}from"./BackspaceIcon.cf61c23a.js";import{r as W}from"./PencilSquareIcon.ae5def20.js";import{r as X}from"./TrashIcon.010d1f9c.js";import"./open-closed.bf81ab9b.js";import"./use-resolve-button-type.83a8e777.js";import"./RectangleStackIcon.31fd4410.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Cashless Terminal) ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},le=e("span",null," Create ",-1),ae={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Provider ",-1),ne={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},ie={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),he={class:"font-medium"},pe=e("span",null,"to",-1),ge={class:"font-medium"},ve=e("span",null,"of",-1),xe={class:"font-medium"},_e=e("span",null,"results",-1),ye={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ce={class:"bg-gray-100"},Te={class:"divide-x divide-gray-200"},Pe={class:"bg-white"},$e={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Se=e("span",null," Delete ",-1),Ve={key:0},Le=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Le],ze={__name:"Index",props:{cashlessProviders:Object,cashlessTerminals:Object},setup(n){const K=n,t=d({code:"",phone_number:"",telco_id:"",sortKey:"",sortBy:!0,numberPerPage:100}),f=d(!1),x=d(),_=d(""),y=d([]),C=d([]);Z(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=y.value[0],C.value=K.cashlessProviders.data.map(r=>({id:r.id,name:r.name}))});function D(){_.value="create",x.value=null,f.value=!0}function F(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/cashless-terminals/"+r.id)}function N(r){_.value="update",x.value=r,f.value=!0}function b(){k.get("/cashless-terminals",{...t.value,telco_id:t.value.telco_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){k.get("/cashless-terminals")}function T(r){t.value.sortKey=r,t.value.sortBy=!t.value.sortBy,b()}function M(){f.value=!1}return(r,l)=>{const O=G("Form");return u(),v(L,null,[s(c(q),{title:"CashlessTerminal"}),s(A,null,{header:o(()=>[Y]),default:o(()=>{var P,$;return[e("div",ee,[e("div",se,[e("div",te,[s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=a=>D())},{default:o(()=>[s(c(J),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",ae,[s(E,{placeholderStr:"Terminal ID",modelValue:t.value.code,"onUpdate:modelValue":l[1]||(l[1]=a=>t.value.code=a)},{default:o(()=>[i(" Terminal ID ")]),_:1},8,["modelValue"]),e("div",null,[oe,s(S,{modelValue:t.value.cashless_provider_id,"onUpdate:modelValue":l[2]||(l[2]=a=>t.value.cashless_provider_id=a),options:C.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",ne,[e("div",re,[e("div",ie,[s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=a=>b())},{default:o(()=>[s(c(R),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[4]||(l[4]=a=>j())},{default:o(()=>[s(c(Q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",ue,[e("p",me,[fe,e("span",he,m((P=n.cashlessTerminals.meta.from)!=null?P:0),1),pe,e("span",ge,m(($=n.cashlessTerminals.meta.to)!=null?$:0),1),ve,e("span",xe,m(n.cashlessTerminals.meta.total),1),_e]),s(S,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":l[5]||(l[5]=a=>t.value.numberPerPage=a),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",ye,[e("div",be,[e("div",we,[e("table",ke,[e("thead",Ce,[e("tr",Te,[s(B,null,{default:o(()=>[i(" # ")]),_:1}),s(V,{modelName:"code",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:l[6]||(l[6]=a=>T("code"))},{default:o(()=>[i(" Terminal ID ")]),_:1},8,["sortKey","sortBy"]),s(V,{modelName:"cashless_provider_id",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:l[7]||(l[7]=a=>T("cashless_provider_id"))},{default:o(()=>[i(" Provider ")]),_:1},8,["sortKey","sortBy"]),s(B)])]),e("tbody",Pe,[(u(!0),v(L,null,z(n.cashlessTerminals.data,(a,h)=>(u(),v("tr",{key:a.id,class:"divide-x divide-gray-200"},[s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-center"},{default:o(()=>[i(m(n.cashlessTerminals.meta.from+h),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-left"},{default:o(()=>[i(m(a.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-left"},{default:o(()=>[i(m(a.cashlessProvider.name),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-center"},{default:o(()=>[e("div",$e,[s(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:U=>N(a)},{default:o(()=>[s(c(W),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),s(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:U=>F(a)},{default:o(()=>[s(c(X),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.cashlessTerminals.data.length?w("",!0):(u(),v("tr",Ve,Ie))])]),n.cashlessTerminals.data.length?(u(),I(H,{key:0,links:n.cashlessTerminals.links,meta:n.cashlessTerminals.meta},null,8,["links","meta"])):w("",!0)])])])]),f.value?(u(),I(O,{key:0,cashlessTerminal:x.value,telcos:r.telcos,type:_.value,showModal:f.value,onModalClose:M},null,8,["cashlessTerminal","telcos","type","showModal"])):w("",!0)]}),_:1})],64)}}};export{ze as default};
